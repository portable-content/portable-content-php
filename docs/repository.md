# Repository Pattern

The Portable Content PHP library uses the Repository pattern to abstract data access and provide a clean interface for content persistence operations.

## Overview

The Repository pattern provides:
- **Abstraction**: Hides database implementation details
- **Testability**: Easy mocking for unit tests
- **Flexibility**: Switch between different storage backends
- **Consistency**: Uniform interface for all data operations

## Interface

### ContentRepositoryInterface

All repository implementations must implement this interface:

```php
interface ContentRepositoryInterface
{
    public function save(ContentItem $content): void;
    public function findById(string $id): ?ContentItem;
    public function findAll(int $limit = 20, int $offset = 0): array;
    public function delete(string $id): void;
}
```

## Implementations

### SQLiteContentRepository

The primary implementation using SQLite database.

#### Features
- **ACID Transactions**: All operations are transaction-safe
- **Foreign Key Constraints**: Ensures referential integrity
- **Prepared Statements**: Prevents SQL injection
- **Error Handling**: Comprehensive exception handling

#### Database Schema

```sql
-- Content items table
CREATE TABLE content_items (
    id TEXT PRIMARY KEY,
    type TEXT NOT NULL DEFAULT 'note',
    title TEXT,
    summary TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- Markdown blocks table
CREATE TABLE markdown_blocks (
    id TEXT PRIMARY KEY,
    content_id TEXT NOT NULL,
    source TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE
);

-- Indexes for performance
CREATE INDEX IF NOT EXISTS idx_content_created ON content_items(created_at);
CREATE INDEX IF NOT EXISTS idx_blocks_content ON markdown_blocks(content_id);
```

#### Usage

```php
use PortableContent\Tests\Support\Repository\RepositoryFactory;

// Create SQLite repository
$repository = RepositoryFactory::createSQLiteRepository('storage/content.db');

// Save content
$content = ContentItem::create('note', 'My Note');
$repository->save($content);

// Retrieve content
$retrieved = $repository->findById($content->id);

// List content with pagination
$allContent = $repository->findAll(limit: 10, offset: 0);

// Delete content
$repository->delete($content->id);
```

### In-Memory Repository

For testing and development purposes.

```php
// Create in-memory repository
$repository = RepositoryFactory::createInMemoryRepository();

// Same interface as SQLite repository
$repository->save($content);
$retrieved = $repository->findById($content->id);
```

## Repository Factory

The `RepositoryFactory` provides convenient methods for creating repository instances.

### Methods

#### `createSQLiteRepository(string $databasePath)`

Creates a SQLite-based repository with the specified database file.

```php
$repository = RepositoryFactory::createSQLiteRepository('storage/content.db');
```

#### `createInMemoryRepository()`

Creates an in-memory SQLite repository (perfect for testing).

```php
$repository = RepositoryFactory::createInMemoryRepository();
```

#### `getDefaultRepository()`

Returns a singleton instance of the default repository (SQLite at `storage/content.db`).

```php
$repository = RepositoryFactory::getDefaultRepository();
```

## Operations

### Save Operation

The save operation handles both creation and updates:

```php
public function save(ContentItem $content): void
{
    try {
        $this->pdo->beginTransaction();

        // Save or update the content item
        $this->saveContentItem($content);

        // Delete existing blocks (for updates)
        $this->deleteContentBlocks($content->id);

        // Save all blocks
        foreach ($content->blocks as $block) {
            if ($block instanceof MarkdownBlock) {
                $this->saveMarkdownBlock($content->id, $block);
            }
        }

        $this->pdo->commit();
    } catch (PDOException $e) {
        $this->pdo->rollBack();
        throw RepositoryException::saveFailure($content->id, $e->getMessage());
    }
}
```

**Key Features:**
- **Transactional**: All-or-nothing operation
- **Upsert Logic**: Handles both new and existing content
- **Block Management**: Replaces all blocks on update
- **Error Recovery**: Automatic rollback on failure

### Find Operations

#### Find by ID

```php
public function findById(string $id): ?ContentItem
{
    try {
        // Query content item
        $stmt = $this->pdo->prepare('SELECT * FROM content_items WHERE id = ?');
        $stmt->execute([$id]);
        $contentData = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$contentData) {
            return null;
        }

        // Query associated blocks
        $stmt = $this->pdo->prepare('SELECT * FROM markdown_blocks WHERE content_id = ? ORDER BY created_at');
        $stmt->execute([$id]);
        $blocksData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Reconstruct domain objects
        $blocks = [];
        foreach ($blocksData as $blockData) {
            $blocks[] = new MarkdownBlock(
                id: $blockData['id'],
                source: $blockData['source'],
                createdAt: new DateTimeImmutable($blockData['created_at'])
            );
        }

        return new ContentItem(
            id: $contentData['id'],
            type: $contentData['type'],
            title: $contentData['title'],
            summary: $contentData['summary'],
            blocks: $blocks,
            createdAt: new DateTimeImmutable($contentData['created_at']),
            updatedAt: new DateTimeImmutable($contentData['updated_at'])
        );

    } catch (PDOException $e) {
        throw RepositoryException::queryFailure('findById', $e->getMessage());
    }
}
```

#### Find All with Pagination

```php
public function findAll(int $limit = 20, int $offset = 0): array
{
    try {
        $stmt = $this->pdo->prepare('
            SELECT * FROM content_items 
            ORDER BY created_at DESC 
            LIMIT ? OFFSET ?
        ');
        $stmt->execute([$limit, $offset]);
        $contentData = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [];
        foreach ($contentData as $data) {
            $content = $this->findById($data['id']);
            if ($content) {
                $results[] = $content;
            }
        }

        return $results;
    } catch (PDOException $e) {
        throw RepositoryException::queryFailure('findAll', $e->getMessage());
    }
}
```

### Delete Operation

```php
public function delete(string $id): void
{
    try {
        $this->pdo->beginTransaction();

        // Delete blocks first (foreign key constraint)
        $stmt = $this->pdo->prepare('DELETE FROM markdown_blocks WHERE content_id = ?');
        $stmt->execute([$id]);

        // Delete content item
        $stmt = $this->pdo->prepare('DELETE FROM content_items WHERE id = ?');
        $stmt->execute([$id]);

        $this->pdo->commit();
    } catch (PDOException $e) {
        $this->pdo->rollBack();
        throw RepositoryException::queryFailure('delete', $e->getMessage());
    }
}
```

## Error Handling

### RepositoryException

All repository errors are wrapped in `RepositoryException`:

```php
class RepositoryException extends Exception
{
    public static function saveFailure(string $contentId, string $reason): self
    {
        return new self("Failed to save content '{$contentId}': {$reason}");
    }

    public static function queryFailure(string $operation, string $reason): self
    {
        return new self("Query failed for operation '{$operation}': {$reason}");
    }

    public static function transactionFailure(string $reason): self
    {
        return new self("Transaction failed: {$reason}");
    }
}
```

### Usage with Error Handling

```php
try {
    $repository->save($content);
    echo "Content saved successfully\n";
} catch (RepositoryException $e) {
    echo "Failed to save content: " . $e->getMessage() . "\n";
    // Log error, notify user, etc.
}
```

## Testing with Repository

### Unit Testing

```php
use PHPUnit\Framework\TestCase;

class ContentServiceTest extends TestCase
{
    private ContentRepositoryInterface $repository;

    protected function setUp(): void
    {
        // Use in-memory repository for fast, isolated tests
        $this->repository = RepositoryFactory::createInMemoryRepository();
    }

    public function testSaveAndRetrieve(): void
    {
        $content = ContentItem::create('test', 'Test Content');
        
        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);
        
        $this->assertNotNull($retrieved);
        $this->assertEquals($content->id, $retrieved->id);
    }
}
```

### Integration Testing

```php
class RepositoryIntegrationTest extends TestCase
{
    public function testSQLiteRepository(): void
    {
        $tempDb = tempnam(sys_get_temp_dir(), 'test_db');
        $repository = RepositoryFactory::createSQLiteRepository($tempDb);
        
        // Test operations
        $content = ContentItem::create('note', 'Integration Test');
        $repository->save($content);
        
        $retrieved = $repository->findById($content->id);
        $this->assertNotNull($retrieved);
        
        // Cleanup
        unlink($tempDb);
    }
}
```

## Performance Considerations

### Indexing

The repository creates indexes on frequently queried columns:

```sql
CREATE INDEX IF NOT EXISTS idx_content_created ON content_items(created_at);
CREATE INDEX IF NOT EXISTS idx_blocks_content ON markdown_blocks(content_id);
```

### Pagination

Always use pagination for large datasets:

```php
// Good: Paginated query
$page1 = $repository->findAll(limit: 20, offset: 0);
$page2 = $repository->findAll(limit: 20, offset: 20);

// Bad: Loading all data
$allData = $repository->findAll(limit: 10000); // Could be slow
```

### Batch Operations

For bulk operations, consider transaction batching:

```php
function saveBatch(ContentRepositoryInterface $repository, array $contentItems): void
{
    foreach ($contentItems as $content) {
        try {
            $repository->save($content);
        } catch (RepositoryException $e) {
            // Log error but continue with other items
            error_log("Failed to save {$content->id}: " . $e->getMessage());
        }
    }
}
```

## Custom Repository Implementation

To create a custom repository (e.g., for PostgreSQL, MongoDB):

```php
class PostgreSQLContentRepository implements ContentRepositoryInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function save(ContentItem $content): void
    {
        // PostgreSQL-specific implementation
        // Handle JSON columns, arrays, etc.
    }

    public function findById(string $id): ?ContentItem
    {
        // PostgreSQL-specific queries
        // Use JSON operators, etc.
    }

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        // PostgreSQL-specific pagination
        // Use LIMIT/OFFSET or cursor-based pagination
    }

    public function delete(string $id): void
    {
        // PostgreSQL-specific deletion
        // Handle cascading deletes
    }
}
```

## Best Practices

### 1. Always Use Transactions

```php
// Good: Transactional
try {
    $pdo->beginTransaction();
    // Multiple operations
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    throw $e;
}
```

### 2. Handle Errors Gracefully

```php
// Good: Proper error handling
try {
    $repository->save($content);
} catch (RepositoryException $e) {
    // Log, notify, retry, etc.
}
```

### 3. Use Appropriate Repository Type

```php
// Testing: Use in-memory
$testRepo = RepositoryFactory::createInMemoryRepository();

// Production: Use persistent storage
$prodRepo = RepositoryFactory::createSQLiteRepository('storage/content.db');
```

### 4. Implement Proper Cleanup

```php
// For temporary databases in tests
protected function tearDown(): void
{
    if (file_exists($this->tempDbPath)) {
        unlink($this->tempDbPath);
    }
}
```

The Repository pattern provides a clean, testable, and flexible approach to data persistence while maintaining separation of concerns and enabling easy testing.
