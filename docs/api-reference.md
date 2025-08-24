# API Reference

Complete API documentation for the Portable Content PHP library.

## Core Classes

### ContentItem

Mutable content item containing metadata and blocks.

#### Constructor

```php
public function __construct(
    string $id,
    string $type,
    ?string $title,
    ?string $summary,
    array $blocks,  // BlockInterface[]
    DateTimeImmutable $createdAt,
    DateTimeImmutable $updatedAt,
)
```

#### Static Methods

##### `create()`

```php
public static function create(
    string $type,
    ?string $title = null,
    ?string $summary = null,
    array $blocks = []
): self
```

Creates a new ContentItem with auto-generated ID and timestamps.

**Parameters:**
- `$type` - Content type (required, alphanumeric + underscore only)
- `$title` - Optional title (max 255 characters)
- `$summary` - Optional summary (max 1000 characters)
- `$blocks` - Array of BlockInterface implementations

**Throws:**
- `InvalidContentException` - If type is empty or blocks are invalid

**Example:**
```php
$content = ContentItem::create('note', 'My Note', 'A simple note');
```

#### Instance Methods

##### Getters

```php
public function getId(): string
public function getType(): string
public function getTitle(): ?string
public function getSummary(): ?string
public function getBlocks(): array  // BlockInterface[]
public function getCreatedAt(): DateTimeImmutable
public function getUpdatedAt(): DateTimeImmutable
```

##### Setters

```php
public function setTitle(?string $title): void
public function setSummary(?string $summary): void
public function setBlocks(array $blocks): void  // Updates updatedAt timestamp
```

##### Block Management

```php
public function addBlock(BlockInterface $block): void  // Updates updatedAt timestamp
```

### AbstractBlock

Base class for all block implementations.

#### Constructor

```php
protected function __construct(
    string $id,
    DateTimeImmutable $createdAt,
)
```

#### Protected Static Methods

```php
protected static function generateId(): string
protected static function generateCreatedAt(): DateTimeImmutable
```

#### Common Methods

```php
public function getId(): string
public function getCreatedAt(): DateTimeImmutable
public function setId(string $id): void
public function setCreatedAt(DateTimeImmutable $createdAt): void
```

#### Abstract Methods

```php
abstract public function isEmpty(): bool
abstract public function getWordCount(): int
abstract public function getType(): string
abstract public function getContent(): string
```

### MarkdownBlock

Mutable markdown block implementation extending AbstractBlock.

#### Constructor

```php
public function __construct(
    string $id,
    string $source,
    DateTimeImmutable $createdAt,
)
```

#### Static Methods

##### `create()`

```php
public static function create(string $source): self
```

Creates a new MarkdownBlock with auto-generated ID and timestamp.

**Parameters:**
- `$source` - Markdown source content (required, non-empty)

**Throws:**
- `InvalidContentException` - If source is empty

#### Instance Methods

##### `getSource()`

```php
public function getSource(): string
```

Returns the markdown source content.

##### `setSource()`

```php
public function setSource(string $source): void
```

Updates the markdown source content.

##### `getId()`

```php
public function getId(): string
```

Returns the block ID.

##### `getCreatedAt()`

```php
public function getCreatedAt(): DateTimeImmutable
```

Returns the creation timestamp.

##### `isEmpty()`

```php
public function isEmpty(): bool
```

Returns true if source content is empty after trimming.

##### `getWordCount()`

```php
public function getWordCount(): int
```

Returns approximate word count of the source content.

## Repository Pattern

### ContentRepositoryInterface

Interface for content persistence operations.

#### Methods

##### `save()`

```php
public function save(ContentItem $content): void
```

Saves or updates a content item with all its blocks.

**Throws:**
- `RepositoryException` - If save operation fails

##### `findById()`

```php
public function findById(string $id): ?ContentItem
```

Retrieves content by ID, or null if not found.

**Throws:**
- `RepositoryException` - If query fails

##### `findAll()`

```php
public function findAll(int $limit = 20, int $offset = 0): array
```

Retrieves all content items with pagination.

**Returns:** Array of ContentItem objects

##### `delete()`

```php
public function delete(string $id): void
```

Deletes content and all associated blocks.

**Throws:**
- `RepositoryException` - If deletion fails

### RepositoryFactory

Factory for creating repository instances.

#### Static Methods

##### `createSQLiteRepository()`

```php
public static function createSQLiteRepository(string $databasePath): ContentRepositoryInterface
```

Creates SQLite-based repository.

##### `createInMemoryRepository()`

```php
public static function createInMemoryRepository(): ContentRepositoryInterface
```

Creates in-memory repository (for testing).

##### `getDefaultRepository()`

```php
public static function getDefaultRepository(): ContentRepositoryInterface
```

Returns default repository instance (SQLite at `storage/content.db`).

## Validation System

### ContentValidationService

Main validation service orchestrating sanitization and validation.

#### Constructor

```php
public function __construct(
    private readonly SanitizerInterface $contentSanitizer,
    private readonly ContentValidatorInterface $contentValidator
)
```

#### Methods

##### `validateContentCreation()`

```php
public function validateContentCreation(array $data): ValidationResult
```

Validates data for content creation (full sanitization + validation).

**Parameters:**
- `$data` - Raw input data array

**Returns:** ValidationResult with sanitized data if valid

##### `validateContentUpdate()`

```php
public function validateContentUpdate(array $data): ValidationResult
```

Validates data for content updates (allows partial data).

##### `sanitizeContent()`

```php
public function sanitizeContent(array $data): array
```

Sanitizes input data without validation.

##### `validateSanitizedContent()`

```php
public function validateSanitizedContent(array $data): ValidationResult
```

Validates pre-sanitized data.

##### `getSanitizationStats()`

```php
public function getSanitizationStats(array $original, array $sanitized): array
```

Returns statistics about sanitization changes.

##### `processContentWithDetails()`

```php
public function processContentWithDetails(array $data): array
```

Processes content with detailed pipeline information.

**Returns:**
```php
[
    'sanitized_data' => array,
    'sanitization_stats' => array,
    'validation_result' => ValidationResult,
    'final_result' => ValidationResult
]
```

### ValidationResult

Value object representing validation outcome.

#### Static Methods

##### `success()`

```php
public static function success(): self
```

Creates successful validation result.

##### `successWithData()`

```php
public static function successWithData(array $data): self
```

Creates successful result with data.

##### `singleError()`

```php
public static function singleError(string $field, string $message): self
```

Creates result with single error.

##### `multipleErrors()`

```php
public static function multipleErrors(array $errors): self
```

Creates result with multiple errors.

#### Instance Methods

##### `isValid()`

```php
public function isValid(): bool
```

Returns true if validation passed.

##### `getErrors()`

```php
public function getErrors(): array
```

Returns all validation errors.

##### `hasFieldErrors()`

```php
public function hasFieldErrors(string $field): bool
```

Returns true if field has errors.

##### `getFieldErrors()`

```php
public function getFieldErrors(string $field): array
```

Returns errors for specific field.

##### `getFirstError()`

```php
public function getFirstError(): ?string
```

Returns first error message.

##### `getData()`

```php
public function getData(): ?array
```

Returns validated/sanitized data if available.

## Exception Classes

### InvalidContentException

Thrown when content creation fails due to invalid parameters.

#### Static Methods

```php
public static function emptyType(): self
public static function emptyBlockSource(): self
public static function invalidBlockType(mixed $block): self
```

### RepositoryException

Thrown when repository operations fail.

#### Static Methods

```php
public static function saveFailure(string $contentId, string $reason): self
public static function queryFailure(string $operation, string $reason): self
public static function transactionFailure(string $reason): self
```

### ValidationException

Thrown when validation fails (if using exception-based validation).

## Block System

### BlockInterface

Interface that all block types must implement.

#### Methods

```php
public function getId(): string
public function getCreatedAt(): DateTimeImmutable
```

### Block Sanitizers

#### MarkdownBlockSanitizer

Sanitizes markdown block data.

##### `sanitize()`

```php
public function sanitize(array $blockData): array
```

Sanitizes markdown block data with markdown-specific rules.

##### `supports()`

```php
public function supports(string $blockType): bool
```

Returns true if sanitizer supports the block type.

### Block Validators

#### MarkdownBlockValidator

Validates markdown block data.

##### `validate()`

```php
public function validate(array $blockData): void
```

Validates markdown block data.

**Throws:**
- `ValidationException` - If validation fails

## Database Utilities

### Database

Database connection and migration utilities.

#### Static Methods

##### `initialize()`

```php
public static function initialize(string $path): PDO
```

Initializes SQLite database with migrations.

##### `createInMemory()`

```php
public static function createInMemory(): PDO
```

Creates in-memory SQLite database.

##### `runMigrations()`

```php
public static function runMigrations(PDO $pdo, bool $verbose = true): void
```

Runs database migrations.

## Constants and Enums

### Validation Limits

```php
const MAX_TITLE_LENGTH = 255;
const MAX_SUMMARY_LENGTH = 1000;
const MAX_BLOCK_SOURCE_LENGTH = 100000; // 100KB
const MAX_BLOCKS_PER_CONTENT = 10;
```

### Block Types

```php
const BLOCK_TYPE_MARKDOWN = 'markdown';
```

## Usage Examples

See [examples.md](examples.md) for comprehensive usage examples and common patterns.
