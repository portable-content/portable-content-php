# Hydration Architecture Proposal

## Overview

This document proposes moving hydration logic from repository implementations into the core portable-content-php library using a wrapper-based approach. This change will ensure consistent data representation across all persistence layers while providing both raw JSON (primary use case) and hydrated domain objects (for domain operations).

## Current Problem

Currently, each repository implementation (SQLite, Weaviate) handles its own data mapping/hydration logic. This leads to:

- **Inconsistent data structures** across persistence layers
- **Duplicated hydration logic** in each repository
- **Tight coupling** between domain objects and storage formats
- **Difficult testing** of hydration logic in isolation
- **No access to raw JSON** for API/MCP usage (primary use case)

## Proposed Solution

### 1. Wrapper-Based Repository Results

All repository query methods return wrapper objects that can provide either raw JSON or hydrated domain objects:

```php
// Raw JSON (primary use case for API/MCP)
$json = $repo->findById('test')->asJson(); // ?array

// Domain objects (for domain operations/updates)
$content = $repo->findById('test')->asDomain(); // ?ContentItem

// Existence checking
if ($repo->findById('test')->exists()) {
    // Content found
}
```

### 2. Standard JSON Format

All persistence layers work with the same standard JSON format:

```json
{
  "id": "content-123",
  "type": "article",
  "title": "Example Article",
  "summary": "Article summary",
  "createdAt": "2024-01-01T10:00:00Z",
  "updatedAt": "2024-01-01T10:00:00Z",
  "blocks": [
    {
      "id": "block-456",
      "type": "markdown",
      "source": "# Heading\n\nContent",
      "createdAt": "2024-01-01T10:00:00Z"
    }
  ]
}
```

### 3. Result Wrapper Classes

```php
namespace PortableContent\Repository;

class ContentResult
{
    public function __construct(
        private ?array $data,
        private ContentHydrationInterface $hydrator
    ) {}

    public function asJson(): ?array
    {
        return $this->data;
    }

    public function asDomain(): ?ContentItem
    {
        return $this->data ? $this->hydrator->hydrate($this->data) : null;
    }

    public function exists(): bool
    {
        return $this->data !== null;
    }
}

class ContentResultCollection
{
    public function __construct(
        private array $data,
        private ContentHydrationInterface $hydrator
    ) {}

    public function asJson(): array
    {
        return $this->data;
    }

    public function asDomain(): array
    {
        return array_map(
            fn($item) => $this->hydrator->hydrate($item),
            $this->data
        );
    }

    public function isEmpty(): bool
    {
        return empty($this->data);
    }

    public function count(): int
    {
        return count($this->data);
    }
}
```

### 4. Block-Specific Hydration

Each block type implements its own hydration interface with manual registration:

```php
interface BlockHydrationInterface
{
    public function dehydrate(BlockInterface $block): array;
    public function hydrate(array $data): BlockInterface;
    public function supports(string $blockType): bool;
}

// Located in src/Block/Markdown/MarkdownBlockHydration.php
class MarkdownBlockHydration implements BlockHydrationInterface
{
    public function dehydrate(BlockInterface $block): array
    {
        assert($block instanceof MarkdownBlock);

        return [
            'id' => $block->getId(),
            'type' => $block->getType(),
            'source' => $block->getSource(),
            'createdAt' => $block->getCreatedAt()->format(DateTimeInterface::RFC3339),
        ];
    }

    public function hydrate(array $data): BlockInterface
    {
        // Data is trusted - already sanitized/validated before reaching hydration
        return new MarkdownBlock(
            id: $data['id'],
            source: $data['source'],
            createdAt: new DateTimeImmutable($data['createdAt'])
        );
    }

    public function supports(string $blockType): bool
    {
        return $blockType === 'markdown';
    }
}
```

### 5. Block Hydration Registry

Manual registration for now (auto-discovery can be added later):

```php
class BlockHydrationRegistry
{
    /** @var array<string, BlockHydrationInterface> */
    private array $hydrators = [];

    public function register(BlockHydrationInterface $hydrator): void
    {
        $this->hydrators[$hydrator->getBlockType()] = $hydrator;
    }

    public function dehydrate(BlockInterface $block): array
    {
        $hydrator = $this->getHydrator($block->getType());
        if (!$hydrator) {
            throw new MissingBlockHydratorException($block->getType());
        }
        return $hydrator->dehydrate($block);
    }

    public function hydrate(array $data): BlockInterface
    {
        $hydrator = $this->getHydrator($data['type']);
        if (!$hydrator) {
            throw new MissingBlockHydratorException($data['type']);
        }
        return $hydrator->hydrate($data);
    }

    private function getHydrator(string $blockType): ?BlockHydrationInterface
    {
        return $this->hydrators[$blockType] ?? null;
    }
}
```

### 6. Content Hydration with Block Registry

```php
interface ContentHydrationInterface
{
    public function dehydrate(ContentItem $content): array;
    public function hydrate(array $data): ContentItem;
}

class ContentHydration implements ContentHydrationInterface
{
    public function __construct(
        private readonly BlockHydrationRegistry $blockRegistry
    ) {}

    public function dehydrate(ContentItem $content): array
    {
        return [
            'id' => $content->getId(),
            'type' => $content->getType(),
            'title' => $content->getTitle(),
            'summary' => $content->getSummary(),
            'createdAt' => $content->getCreatedAt()->format(DateTimeInterface::RFC3339),
            'updatedAt' => $content->getUpdatedAt()->format(DateTimeInterface::RFC3339),
            'blocks' => array_map(
                fn($block) => $this->blockRegistry->dehydrate($block),
                $content->getBlocks()
            )
        ];
    }

    public function hydrate(array $data): ContentItem
    {
        // Data is trusted - already sanitized/validated before reaching hydration
        $blocks = array_map(
            fn($blockData) => $this->blockRegistry->hydrate($blockData),
            $data['blocks']
        );

        return new ContentItem(
            id: $data['id'],
            type: $data['type'],
            title: $data['title'],
            summary: $data['summary'],
            blocks: $blocks,
            createdAt: new DateTimeImmutable($data['createdAt']),
            updatedAt: new DateTimeImmutable($data['updatedAt'])
        );
    }
}
```

### 7. Repository Interface Changes

All query methods return wrapper objects that provide both raw JSON and hydrated access:

```php
interface ContentRepositoryInterface
{
    // All query methods return wrappers
    public function findById(string $id): ContentResult;
    public function findAll(int $limit = 20, int $offset = 0): ContentResultCollection;
    public function findByType(string $type, int $limit = 20, int $offset = 0): ContentResultCollection;
    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): ContentResultCollection;
    public function search(string $query, int $limit = 10): ContentResultCollection;
    public function findSimilar(ContentItem|array $content, int $limit = 10): ContentResultCollection;

    // Non-query methods unchanged
    public function save(ContentItem $content): void;
    public function delete(string $id): void;
    public function exists(string $id): bool;
    public function count(): int;
    public function getCapabilities(): array;
    public function supports(string $capability): bool;
}
```

### 8. ContentResultFactory

To handle dependency injection and avoid tight coupling:

```php
interface ContentResultFactoryInterface
{
    public function createResult(?array $data): ContentResult;
    public function createCollection(array $data): ContentResultCollection;
}

class ContentResultFactory implements ContentResultFactoryInterface
{
    public function __construct(
        private ContentHydrationInterface $hydrator
    ) {}

    public function createResult(?array $data): ContentResult
    {
        return new ContentResult($data, $this->hydrator);
    }

    public function createCollection(array $data): ContentResultCollection
    {
        return new ContentResultCollection($data, $this->hydrator);
    }
}
```

## Implementation Plan

### Phase 1: Core Hydration Infrastructure

1. **Create hydration interfaces**:
   - `BlockHydrationInterface` in `src/Contracts/Hydration/`
   - `ContentHydrationInterface` in `src/Contracts/Hydration/`
   - `BlockHydrationRegistry` with manual registration (auto-discovery later)

2. **Implement block hydrations**:
   - `MarkdownBlockHydration` in `src/Block/Markdown/`
   - Manual registry registration for now
   - Hard-fail when hydrator missing

3. **Create content hydration**:
   - `ContentHydration` implementation in `src/Hydration/`
   - Integration with block registry
   - No validation - data is trusted from database/transformation

### Phase 2: Result Wrapper Classes

1. **Create wrapper classes** in `PortableContent\Repository` namespace:
   - `ContentResult` with `asJson()`, `asDomain()`, `exists()`
   - `ContentResultCollection` with `asJson()`, `asDomain()`, `isEmpty()`, `count()`

2. **Create factory**:
   - `ContentResultFactoryInterface`
   - `ContentResultFactory` implementation
   - Handle dependency injection for hydration service

### Phase 3: Repository Interface Updates

1. **Update `ContentRepositoryInterface`**:
   - Change all query methods to return wrapper objects
   - Update `findSimilar()` to accept `ContentItem|array`
   - Keep non-query methods unchanged

2. **Create exception classes**:
   - `ContentHydrationException`
   - `MissingBlockHydratorException`
   - `InvalidContentDataException`

### Phase 4: Repository Implementation Updates

1. **Update SQLite repository**:
   - Inject `ContentResultFactory`
   - Return wrapper objects from all query methods
   - Handle internal JSON format transformations
   - Proof of concept implementation

2. **Update other repositories**:
   - Apply same pattern to Weaviate and other implementations
   - Each handles its own format transformations internally

### Phase 5: Testing & Migration

1. **Create new tests**:
   - Unit tests for hydration logic in isolation
   - Integration tests for repository + hydration
   - Cross-repository compatibility tests

2. **Update existing tests**:
   - Migrate existing tests to use new wrapper API
   - Ensure backward compatibility during transition

3. **Documentation & Migration Guide**:
   - Update all documentation and examples
   - Create clear migration guide with before/after examples
   - Document breaking changes for major version bump

## Benefits

### 1. Primary Use Case Support
- **Raw JSON first**: API/MCP usage gets direct JSON access without hydration overhead
- **Domain objects when needed**: Hydration only for domain operations/updates
- **Single interface**: One method call provides both options

### 2. Consistency
- Same data structure across all persistence layers
- Predictable JSON format for API responses
- Easier data migration between storage systems

### 3. Separation of Concerns
- **Domain logic stays in domain layer**: Hydration logic with domain objects
- **Repository focuses on storage**: Only handles persistence operations
- **Clear boundaries**: Wrapper handles format conversion

### 4. Extensibility
- **Easy to add new block types**: Just create hydrator with attribute
- **Auto-discovery**: Registry finds hydrators automatically
- **Hard-fail safety**: Missing hydrators cause immediate errors

### 5. Testability
- **Hydration logic isolated**: Can be tested independently of repositories
- **Integration testing**: Repository + hydration work together
- **Mock-friendly**: Easy to mock wrapper objects

### 6. Clean API Design
- **Fluent interface**: `$repo->findById($id)->asJson()`
- **Explicit existence checking**: `->exists()` and `->isEmpty()` methods
- **No double null-checking**: Wrapper always returned, methods return null if not found

## Error Handling Strategy

Following a **hard-fail approach** for developer errors:

```php
class BlockHydrationRegistry
{
    public function hydrate(array $blockData): BlockInterface
    {
        $hydrator = $this->getHydrator($blockData['type']);
        if (!$hydrator) {
            throw new MissingBlockHydratorException(
                "No hydrator found for block type: {$blockData['type']}"
            );
        }
        return $hydrator->hydrate($blockData);
    }
}

class ContentResult
{
    public function asDomain(): ?ContentItem
    {
        if ($this->data === null) {
            return null;
        }

        try {
            return $this->hydrator->hydrate($this->data);
        } catch (HydrationException $e) {
            throw new ContentHydrationException(
                "Failed to hydrate content: " . $e->getMessage(),
                previous: $e
            );
        }
    }
}
```

**No fallbacks, no partials** - if data is wrong or hydrator is missing, fail immediately so developers know about the problem.

## Migration Strategy

This is a **major breaking change** requiring version bump to 2.0.0:

### Before (v1.x)
```php
$content = $repo->findById('test'); // ContentItem|null
$contents = $repo->findAll(); // array<ContentItem>
```

### After (v2.0)
```php
$content = $repo->findById('test')->asDomain(); // ContentItem|null
$json = $repo->findById('test')->asJson(); // array|null
$contents = $repo->findAll()->asDomain(); // array<ContentItem>
$jsonArray = $repo->findAll()->asJson(); // array<array>
```

### Migration Steps
1. **Clear migration guide** with search/replace patterns
2. **Updated documentation** and examples
3. **Feature branch development** with proof of concept
4. **Comprehensive testing** of both unit and integration scenarios

## Key Architectural Decisions

1. **Wrapper objects always returned** - no null checking for wrappers
2. **Raw JSON is primary use case** - optimized for API/MCP scenarios
3. **Repositories don't hydrate** - ContentResult handles hydration
4. **Hard-fail on errors** - missing hydrators and bad data throw exceptions
5. **Manual registration for now** - block hydrators registered manually (auto-discovery later)
6. **Factory pattern for DI** - ContentResultFactory handles hydration service injection
7. **Block hydrators co-located** - each block type has its hydrator in the same directory
8. **Trusted data** - no validation in hydration layer, data from database/transformation is trusted
9. **All blocks follow same pattern** - consistent hydration interface across all block types

## File Structure

```
src/
├── Block/
│   └── Markdown/
│       ├── MarkdownBlock.php
│       ├── MarkdownBlockSanitizer.php
│       ├── MarkdownBlockValidator.php
│       └── MarkdownBlockHydration.php        # New
├── Contracts/
│   └── Hydration/                            # New
│       ├── BlockHydrationInterface.php       # New
│       └── ContentHydrationInterface.php     # New
├── Hydration/                                # New
│   ├── BlockHydrationRegistry.php            # New
│   └── ContentHydration.php                  # New
├── Repository/                               # New
│   ├── ContentResult.php                     # New
│   ├── ContentResultCollection.php           # New
│   ├── ContentResultFactory.php              # New
│   └── ContentResultFactoryInterface.php     # New
```

This architecture provides a clean separation between raw data access and domain object hydration while prioritizing the primary use case of JSON data for APIs and MCP scenarios.

