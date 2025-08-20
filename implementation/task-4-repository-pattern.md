# Task 4: Repository Pattern - Detailed Steps

## Overview
Implement the repository pattern to connect the PHP data classes (ContentItem, MarkdownBlock) to the SQLite database. This creates a clean data access layer with proper transaction handling and error management.

**Estimated Time:** 2-3 hours  
**Dependencies:** Task 3 (Database Schema) must be completed

---

## Step 4.1: Design the Repository Interface
**Time:** 10-15 minutes

### Instructions:
Before implementing, let's define exactly what operations we need:

**Core Operations:**
- `save(ContentItem)` - Insert or update content with all blocks
- `findById(string)` - Retrieve content by ID with blocks loaded
- `findAll(limit, offset)` - List content with pagination
- `delete(string)` - Remove content and cascade delete blocks

**Design Decisions:**
- **Interface-based**: Easy to swap implementations later
- **Transaction handling**: Save operations are atomic
- **Lazy loading**: Blocks loaded when content is retrieved
- **Exception handling**: Clear error messages for failures

### Repository Contract:
```php
interface ContentRepositoryInterface
{
    public function save(ContentItem $content): void;
    public function findById(string $id): ?ContentItem;
    public function findAll(int $limit = 20, int $offset = 0): array;
    public function delete(string $id): void;
    public function count(): int;
}
```

### Validation:
- [ ] Interface defines all needed operations
- [ ] Method signatures are clear and consistent
- [ ] Return types are appropriate
- [ ] Operations cover all use cases

---

## Step 4.2: Create Repository Interface
**Time:** 5 minutes

### Instructions:
1. Create `src/Repository/ContentRepositoryInterface.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Repository;

use PortableContent\ContentItem;

interface ContentRepositoryInterface
{
    /**
     * Save a ContentItem and all its blocks to the database.
     * If the content already exists, it will be updated.
     */
    public function save(ContentItem $content): void;

    /**
     * Find a ContentItem by its ID, including all blocks.
     * Returns null if not found.
     */
    public function findById(string $id): ?ContentItem;

    /**
     * Find all ContentItems with pagination.
     * Returns array of ContentItem objects.
     */
    public function findAll(int $limit = 20, int $offset = 0): array;

    /**
     * Delete a ContentItem and all its blocks.
     * Does nothing if the content doesn't exist.
     */
    public function delete(string $id): void;

    /**
     * Count total number of ContentItems.
     */
    public function count(): int;

    /**
     * Check if a ContentItem exists by ID.
     */
    public function exists(string $id): bool;
}
```

### Validation:
- [ ] Interface file is created
- [ ] All methods are documented
- [ ] Type hints are correct
- [ ] Namespace is appropriate

---

## Step 4.3: Create Repository Exception Classes
**Time:** 10 minutes

### Instructions:
1. Create `src/Repository/RepositoryException.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Repository;

use RuntimeException;

class RepositoryException extends RuntimeException
{
    public static function saveFailure(string $contentId, string $reason): self
    {
        return new self("Failed to save content '{$contentId}': {$reason}");
    }

    public static function deleteFailure(string $contentId, string $reason): self
    {
        return new self("Failed to delete content '{$contentId}': {$reason}");
    }

    public static function queryFailure(string $operation, string $reason): self
    {
        return new self("Failed to execute {$operation}: {$reason}");
    }

    public static function transactionFailure(string $reason): self
    {
        return new self("Transaction failed: {$reason}");
    }
}
```

2. Create `src/Repository/ContentNotFoundException.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Repository;

use RuntimeException;

final class ContentNotFoundException extends RuntimeException
{
    public function __construct(string $contentId)
    {
        parent::__construct("Content with ID '{$contentId}' not found");
    }
}
```

### Validation:
- [ ] Exception classes are created
- [ ] Static factory methods provide context
- [ ] Error messages are descriptive
- [ ] Inheritance hierarchy is appropriate

---

## Step 4.4: Implement SQLite Repository - Part 1 (Basic Structure)
**Time:** 20-25 minutes

### Instructions:
1. Create `src/Repository/SQLiteContentRepository.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Repository;

use DateTimeImmutable;
use PDO;
use PDOException;
use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;

final class SQLiteContentRepository implements ContentRepositoryInterface
{
    public function __construct(private PDO $pdo)
    {
        // Ensure foreign keys are enabled
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

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
                $this->saveMarkdownBlock($content->id, $block);
            }

            $this->pdo->commit();
        } catch (PDOException $e) {
            $this->pdo->rollBack();
            throw RepositoryException::saveFailure($content->id, $e->getMessage());
        } catch (\Exception $e) {
            $this->pdo->rollBack();
            throw RepositoryException::transactionFailure($e->getMessage());
        }
    }

    public function findById(string $id): ?ContentItem
    {
        try {
            // Load content item
            $contentData = $this->loadContentItem($id);
            if ($contentData === null) {
                return null;
            }

            // Load blocks
            $blocks = $this->loadContentBlocks($id);

            // Reconstruct ContentItem
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

    public function findAll(int $limit = 20, int $offset = 0): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM content_items 
                ORDER BY created_at DESC 
                LIMIT ? OFFSET ?
            ');
            $stmt->execute([$limit, $offset]);
            $contentRows = $stmt->fetchAll();

            $results = [];
            foreach ($contentRows as $row) {
                $content = $this->findById($row['id']);
                if ($content !== null) {
                    $results[] = $content;
                }
            }

            return $results;
        } catch (PDOException $e) {
            throw RepositoryException::queryFailure('findAll', $e->getMessage());
        }
    }

    public function delete(string $id): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM content_items WHERE id = ?');
            $stmt->execute([$id]);
            // Blocks are automatically deleted due to CASCADE constraint
        } catch (PDOException $e) {
            throw RepositoryException::deleteFailure($id, $e->getMessage());
        }
    }

    public function count(): int
    {
        try {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM content_items');
            return (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            throw RepositoryException::queryFailure('count', $e->getMessage());
        }
    }

    public function exists(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM content_items WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);
            return $stmt->fetch() !== false;
        } catch (PDOException $e) {
            throw RepositoryException::queryFailure('exists', $e->getMessage());
        }
    }

    // Private helper methods will be implemented in next step
    private function saveContentItem(ContentItem $content): void
    {
        // Implementation coming in Step 4.5
    }

    private function deleteContentBlocks(string $contentId): void
    {
        // Implementation coming in Step 4.5
    }

    private function saveMarkdownBlock(string $contentId, MarkdownBlock $block): void
    {
        // Implementation coming in Step 4.5
    }

    private function loadContentItem(string $id): ?array
    {
        // Implementation coming in Step 4.5
    }

    private function loadContentBlocks(string $contentId): array
    {
        // Implementation coming in Step 4.5
    }
}
```

### Key Design Features:
- **Transaction safety**: All saves are atomic
- **Foreign key enforcement**: Enabled in constructor
- **Proper error handling**: PDO exceptions wrapped
- **Lazy loading**: Blocks loaded when needed
- **Helper methods**: Private methods for database operations

### Validation:
- [ ] Repository class is created
- [ ] Implements the interface correctly
- [ ] Transaction handling is in place
- [ ] Error handling wraps PDO exceptions
- [ ] Method signatures match interface

---

## Step 4.5: Implement SQLite Repository - Part 2 (Helper Methods)
**Time:** 25-30 minutes

### Instructions:
1. Replace the empty helper methods in `SQLiteContentRepository.php`:

```php
    private function saveContentItem(ContentItem $content): void
    {
        $stmt = $this->pdo->prepare('
            INSERT OR REPLACE INTO content_items 
            (id, type, title, summary, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');

        $stmt->execute([
            $content->id,
            $content->type,
            $content->title,
            $content->summary,
            $content->createdAt->format('c'),
            $content->updatedAt->format('c')
        ]);
    }

    private function deleteContentBlocks(string $contentId): void
    {
        $stmt = $this->pdo->prepare('DELETE FROM markdown_blocks WHERE content_id = ?');
        $stmt->execute([$contentId]);
    }

    private function saveMarkdownBlock(string $contentId, MarkdownBlock $block): void
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO markdown_blocks (id, content_id, source, created_at)
            VALUES (?, ?, ?, ?)
        ');

        $stmt->execute([
            $block->id,
            $contentId,
            $block->source,
            $block->createdAt->format('c')
        ]);
    }

    private function loadContentItem(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM content_items WHERE id = ?');
        $stmt->execute([$id]);
        
        $result = $stmt->fetch();
        return $result === false ? null : $result;
    }

    private function loadContentBlocks(string $contentId): array
    {
        $stmt = $this->pdo->prepare('
            SELECT * FROM markdown_blocks 
            WHERE content_id = ? 
            ORDER BY created_at ASC
        ');
        $stmt->execute([$contentId]);
        $blockRows = $stmt->fetchAll();

        $blocks = [];
        foreach ($blockRows as $row) {
            $blocks[] = new MarkdownBlock(
                id: $row['id'],
                source: $row['source'],
                createdAt: new DateTimeImmutable($row['created_at'])
            );
        }

        return $blocks;
    }
```

### Key Implementation Details:
- **INSERT OR REPLACE**: Handles both create and update operations
- **ISO 8601 timestamps**: Using `format('c')` for consistency
- **Ordered block loading**: Blocks loaded in creation order
- **Null handling**: Proper null checks for optional fields
- **Type reconstruction**: Proper object reconstruction from database

### Validation:
- [ ] All helper methods are implemented
- [ ] SQL queries are correct and safe
- [ ] Timestamp formatting is consistent
- [ ] Object reconstruction works properly
- [ ] Null values are handled correctly

---

## Step 4.6: Create Repository Factory
**Time:** 10-15 minutes

### Instructions:
1. Create `src/Repository/RepositoryFactory.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Repository;

use PDO;
use PortableContent\Database\Database;

final class RepositoryFactory
{
    private static ?ContentRepositoryInterface $instance = null;
    private static ?PDO $pdo = null;

    public static function createSQLiteRepository(string $databasePath): ContentRepositoryInterface
    {
        $pdo = Database::initialize($databasePath);
        return new SQLiteContentRepository($pdo);
    }

    public static function createInMemoryRepository(): ContentRepositoryInterface
    {
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo);
        return new SQLiteContentRepository($pdo);
    }

    public static function getDefaultRepository(): ContentRepositoryInterface
    {
        if (self::$instance === null) {
            self::$instance = self::createSQLiteRepository('storage/content.db');
        }
        return self::$instance;
    }

    public static function setDefaultRepository(ContentRepositoryInterface $repository): void
    {
        self::$instance = $repository;
    }

    public static function resetDefault(): void
    {
        self::$instance = null;
        self::$pdo = null;
    }

    /**
     * Create repository with existing PDO connection
     */
    public static function createWithPDO(PDO $pdo): ContentRepositoryInterface
    {
        return new SQLiteContentRepository($pdo);
    }
}
```

### Key Features:
- **Multiple creation methods**: File, in-memory, existing PDO
- **Singleton pattern**: Default repository for convenience
- **Testing support**: Easy to create test repositories
- **Dependency injection**: Can inject custom repositories

### Validation:
- [ ] Factory class is created
- [ ] Multiple creation methods work
- [ ] Singleton pattern is implemented
- [ ] Testing support is available

---

## Step 4.7: Test the Repository Implementation
**Time:** 20-25 minutes

### Instructions:
1. Create `test_repository.php`:

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Repository\RepositoryFactory;

echo "Testing Repository Implementation...\n\n";

// Test 1: Create in-memory repository
echo "1. Creating in-memory repository:\n";
$repository = RepositoryFactory::createInMemoryRepository();
echo "   SUCCESS: Repository created\n\n";

// Test 2: Save content with blocks
echo "2. Testing save operation:\n";
$block1 = MarkdownBlock::create('# Hello World\n\nThis is the first block.');
$block2 = MarkdownBlock::create('## Second Block\n\nThis is the second block.');

$content = ContentItem::create('note', 'Test Note', 'A test note with multiple blocks')
    ->addBlock($block1)
    ->addBlock($block2);

$repository->save($content);
echo "   SUCCESS: Content saved with ID: {$content->id}\n";
echo "   Blocks saved: " . count($content->blocks) . "\n\n";

// Test 3: Retrieve content by ID
echo "3. Testing findById operation:\n";
$retrieved = $repository->findById($content->id);
if ($retrieved !== null) {
    echo "   SUCCESS: Content retrieved\n";
    echo "   Title: {$retrieved->title}\n";
    echo "   Type: {$retrieved->type}\n";
    echo "   Blocks: " . count($retrieved->blocks) . "\n";
    echo "   First block: " . substr($retrieved->blocks[0]->source, 0, 20) . "...\n\n";
} else {
    echo "   ERROR: Content not found\n\n";
}

// Test 4: Test count and exists
echo "4. Testing count and exists:\n";
$count = $repository->count();
$exists = $repository->exists($content->id);
echo "   Total content items: {$count}\n";
echo "   Content exists: " . ($exists ? 'YES' : 'NO') . "\n\n";

// Test 5: Save another content item
echo "5. Testing multiple content items:\n";
$content2 = ContentItem::create('article', 'Second Article', 'Another test article')
    ->addBlock(MarkdownBlock::create('# Article Content\n\nThis is an article.'));

$repository->save($content2);
echo "   SUCCESS: Second content saved\n";

$newCount = $repository->count();
echo "   New total count: {$newCount}\n\n";

// Test 6: Test findAll with pagination
echo "6. Testing findAll operation:\n";
$allContent = $repository->findAll(10, 0);
echo "   Retrieved " . count($allContent) . " items\n";
foreach ($allContent as $item) {
    echo "   - {$item->title} ({$item->type}) - " . count($item->blocks) . " blocks\n";
}
echo "\n";

// Test 7: Test update operation
echo "7. Testing update operation:\n";
$updatedContent = $retrieved->withTitle('Updated Title')
    ->addBlock(MarkdownBlock::create('# New Block\n\nThis is a new block added during update.'));

$repository->save($updatedContent);
echo "   SUCCESS: Content updated\n";

$reRetrieved = $repository->findById($content->id);
echo "   New title: {$reRetrieved->title}\n";
echo "   New block count: " . count($reRetrieved->blocks) . "\n\n";

// Test 8: Test delete operation
echo "8. Testing delete operation:\n";
$repository->delete($content2->id);
echo "   SUCCESS: Content deleted\n";

$finalCount = $repository->count();
$stillExists = $repository->exists($content2->id);
echo "   Final count: {$finalCount}\n";
echo "   Deleted content still exists: " . ($stillExists ? 'YES' : 'NO') . "\n\n";

// Test 9: Test error handling
echo "9. Testing error handling:\n";
$nonExistent = $repository->findById('non-existent-id');
echo "   Non-existent content: " . ($nonExistent === null ? 'NULL (correct)' : 'FOUND (error)') . "\n";

echo "\nRepository tests completed successfully!\n";
```

2. Run the test:
```bash
php test_repository.php
```

### Expected Output:
```
Testing Repository Implementation...

1. Creating in-memory repository:
Applied migration: 001_create_tables.sql
   SUCCESS: Repository created

2. Testing save operation:
   SUCCESS: Content saved with ID: 12345678-1234-1234-1234-123456789abc
   Blocks saved: 2

3. Testing findById operation:
   SUCCESS: Content retrieved
   Title: Test Note
   Type: note
   Blocks: 2
   First block: # Hello World...

4. Testing count and exists:
   Total content items: 1
   Content exists: YES

5. Testing multiple content items:
   SUCCESS: Second content saved
   New total count: 2

6. Testing findAll operation:
   Retrieved 2 items
   - Second Article (article) - 1 blocks
   - Test Note (note) - 2 blocks

7. Testing update operation:
   SUCCESS: Content updated
   New title: Updated Title
   New block count: 3

8. Testing delete operation:
   SUCCESS: Content deleted
   Final count: 1
   Deleted content still exists: NO

9. Testing error handling:
   Non-existent content: NULL (correct)

Repository tests completed successfully!
```

### Validation:
- [ ] All repository operations work correctly
- [ ] Transactions maintain data integrity
- [ ] Blocks are properly associated with content
- [ ] Updates work correctly
- [ ] Delete operations cascade properly
- [ ] Error handling works as expected

---

## Step 4.8: Create Repository Unit Tests
**Time:** 20-25 minutes

### Instructions:
1. Create `tests/Unit/Repository/SQLiteContentRepositoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Repository;

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Repository\ContentNotFoundException;
use PortableContent\Repository\RepositoryException;
use PortableContent\Repository\SQLiteContentRepository;
use PortableContent\Tests\TestCase;

final class SQLiteContentRepositoryTest extends TestCase
{
    private SQLiteContentRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new SQLiteContentRepository($this->createTestDatabase());
    }

    public function testSaveAndFindById(): void
    {
        $block = MarkdownBlock::create('# Test Content\n\nThis is a test.');
        $content = ContentItem::create('note', 'Test Note', 'A test note')
            ->addBlock($block);

        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);

        $this->assertNotNull($retrieved);
        $this->assertEquals($content->id, $retrieved->id);
        $this->assertEquals($content->type, $retrieved->type);
        $this->assertEquals($content->title, $retrieved->title);
        $this->assertEquals($content->summary, $retrieved->summary);
        $this->assertCount(1, $retrieved->blocks);
        $this->assertEquals($block->source, $retrieved->blocks[0]->source);
    }

    public function testFindByIdReturnsNullForNonExistent(): void
    {
        $result = $this->repository->findById('non-existent-id');
        $this->assertNull($result);
    }

    public function testSaveUpdatesExistingContent(): void
    {
        $content = ContentItem::create('note', 'Original Title');
        $this->repository->save($content);

        $updated = $content->withTitle('Updated Title');
        $this->repository->save($updated);

        $retrieved = $this->repository->findById($content->id);
        $this->assertEquals('Updated Title', $retrieved->title);
    }

    public function testSaveWithMultipleBlocks(): void
    {
        $block1 = MarkdownBlock::create('# Block 1');
        $block2 = MarkdownBlock::create('# Block 2');
        $content = ContentItem::create('note', 'Multi-block Note')
            ->addBlock($block1)
            ->addBlock($block2);

        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);

        $this->assertCount(2, $retrieved->blocks);
        $this->assertEquals('# Block 1', $retrieved->blocks[0]->source);
        $this->assertEquals('# Block 2', $retrieved->blocks[1]->source);
    }

    public function testFindAll(): void
    {
        $content1 = ContentItem::create('note', 'Note 1');
        $content2 = ContentItem::create('article', 'Article 1');

        $this->repository->save($content1);
        $this->repository->save($content2);

        $results = $this->repository->findAll(10, 0);

        $this->assertCount(2, $results);
        // Results should be ordered by created_at DESC
        $this->assertEquals('Article 1', $results[0]->title);
        $this->assertEquals('Note 1', $results[1]->title);
    }

    public function testFindAllWithPagination(): void
    {
        for ($i = 1; $i <= 5; $i++) {
            $content = ContentItem::create('note', "Note {$i}");
            $this->repository->save($content);
        }

        $page1 = $this->repository->findAll(2, 0);
        $page2 = $this->repository->findAll(2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(2, $page2);
        $this->assertNotEquals($page1[0]->id, $page2[0]->id);
    }

    public function testDelete(): void
    {
        $content = ContentItem::create('note', 'To Delete')
            ->addBlock(MarkdownBlock::create('# Content'));

        $this->repository->save($content);
        $this->assertTrue($this->repository->exists($content->id));

        $this->repository->delete($content->id);
        $this->assertFalse($this->repository->exists($content->id));
        $this->assertNull($this->repository->findById($content->id));
    }

    public function testDeleteNonExistentDoesNotThrow(): void
    {
        $this->repository->delete('non-existent-id');
        $this->assertTrue(true); // Should not throw
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->repository->count());

        $this->repository->save(ContentItem::create('note', 'Note 1'));
        $this->assertEquals(1, $this->repository->count());

        $this->repository->save(ContentItem::create('note', 'Note 2'));
        $this->assertEquals(2, $this->repository->count());
    }

    public function testExists(): void
    {
        $content = ContentItem::create('note', 'Test');

        $this->assertFalse($this->repository->exists($content->id));

        $this->repository->save($content);
        $this->assertTrue($this->repository->exists($content->id));
    }

    public function testBlocksAreDeletedWhenContentIsUpdated(): void
    {
        $originalBlock = MarkdownBlock::create('# Original');
        $content = ContentItem::create('note', 'Test')
            ->addBlock($originalBlock);

        $this->repository->save($content);

        $newBlock = MarkdownBlock::create('# New Block');
        $updated = $content->withBlocks([$newBlock]);
        $this->repository->save($updated);

        $retrieved = $this->repository->findById($content->id);
        $this->assertCount(1, $retrieved->blocks);
        $this->assertEquals('# New Block', $retrieved->blocks[0]->source);
    }
}
```

2. Create `tests/Unit/Repository/RepositoryFactoryTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Repository;

use PortableContent\Repository\ContentRepositoryInterface;
use PortableContent\Repository\RepositoryFactory;
use PortableContent\Repository\SQLiteContentRepository;
use PortableContent\Tests\TestCase;

final class RepositoryFactoryTest extends TestCase
{
    protected function tearDown(): void
    {
        RepositoryFactory::resetDefault();
        parent::tearDown();
    }

    public function testCreateInMemoryRepository(): void
    {
        $repository = RepositoryFactory::createInMemoryRepository();

        $this->assertInstanceOf(ContentRepositoryInterface::class, $repository);
        $this->assertInstanceOf(SQLiteContentRepository::class, $repository);
    }

    public function testCreateWithPDO(): void
    {
        $pdo = $this->createTestDatabase();
        $repository = RepositoryFactory::createWithPDO($pdo);

        $this->assertInstanceOf(ContentRepositoryInterface::class, $repository);
        $this->assertInstanceOf(SQLiteContentRepository::class, $repository);
    }

    public function testGetDefaultRepository(): void
    {
        $repository1 = RepositoryFactory::getDefaultRepository();
        $repository2 = RepositoryFactory::getDefaultRepository();

        $this->assertSame($repository1, $repository2); // Should be singleton
    }

    public function testSetDefaultRepository(): void
    {
        $customRepository = RepositoryFactory::createInMemoryRepository();
        RepositoryFactory::setDefaultRepository($customRepository);

        $retrieved = RepositoryFactory::getDefaultRepository();
        $this->assertSame($customRepository, $retrieved);
    }

    public function testResetDefault(): void
    {
        $repository1 = RepositoryFactory::getDefaultRepository();
        RepositoryFactory::resetDefault();
        $repository2 = RepositoryFactory::getDefaultRepository();

        $this->assertNotSame($repository1, $repository2);
    }
}
```

3. Run the repository tests:
```bash
./vendor/bin/phpunit --testsuite=Unit tests/Unit/Repository/
```

### Validation:
- [ ] Repository unit tests are created
- [ ] All CRUD operations are tested
- [ ] Error conditions are tested
- [ ] Factory pattern is tested
- [ ] All tests pass successfully

---

## Step 4.9: Create Integration Tests
**Time:** 15-20 minutes

### Instructions:
1. Create `tests/Integration/ContentWorkflowTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Repository\RepositoryFactory;
use PortableContent\Tests\TestCase;

final class ContentWorkflowTest extends TestCase
{
    public function testCompleteContentLifecycle(): void
    {
        $repository = RepositoryFactory::createInMemoryRepository();

        // 1. Create content with blocks
        $block1 = MarkdownBlock::create('# Introduction\n\nThis is the introduction.');
        $block2 = MarkdownBlock::create('## Details\n\nHere are the details.');

        $content = ContentItem::create('article', 'Complete Article', 'A comprehensive article')
            ->addBlock($block1)
            ->addBlock($block2);

        // 2. Save content
        $repository->save($content);
        $this->assertEquals(1, $repository->count());

        // 3. Retrieve and verify
        $retrieved = $repository->findById($content->id);
        $this->assertNotNull($retrieved);
        $this->assertEquals('Complete Article', $retrieved->title);
        $this->assertCount(2, $retrieved->blocks);

        // 4. Update content
        $newBlock = MarkdownBlock::create('## Conclusion\n\nThis is the conclusion.');
        $updated = $retrieved
            ->withTitle('Updated Article')
            ->addBlock($newBlock);

        $repository->save($updated);

        // 5. Verify update
        $reRetrieved = $repository->findById($content->id);
        $this->assertEquals('Updated Article', $reRetrieved->title);
        $this->assertCount(3, $reRetrieved->blocks);

        // 6. Test listing
        $allContent = $repository->findAll();
        $this->assertCount(1, $allContent);
        $this->assertEquals('Updated Article', $allContent[0]->title);

        // 7. Delete content
        $repository->delete($content->id);
        $this->assertEquals(0, $repository->count());
        $this->assertNull($repository->findById($content->id));
    }

    public function testMultipleContentItems(): void
    {
        $repository = RepositoryFactory::createInMemoryRepository();

        // Create multiple content items
        $note = ContentItem::create('note', 'Quick Note')
            ->addBlock(MarkdownBlock::create('# Quick thought\n\nJust a quick note.'));

        $article = ContentItem::create('article', 'Long Article')
            ->addBlock(MarkdownBlock::create('# Article Title\n\nThis is a long article.'))
            ->addBlock(MarkdownBlock::create('## Section 1\n\nFirst section.'))
            ->addBlock(MarkdownBlock::create('## Section 2\n\nSecond section.'));

        $draft = ContentItem::create('draft', 'Work in Progress');

        // Save all
        $repository->save($note);
        $repository->save($article);
        $repository->save($draft);

        // Verify count
        $this->assertEquals(3, $repository->count());

        // Test pagination
        $page1 = $repository->findAll(2, 0);
        $page2 = $repository->findAll(2, 2);

        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);

        // Verify content integrity
        $retrievedArticle = $repository->findById($article->id);
        $this->assertCount(3, $retrievedArticle->blocks);

        $retrievedNote = $repository->findById($note->id);
        $this->assertCount(1, $retrievedNote->blocks);

        $retrievedDraft = $repository->findById($draft->id);
        $this->assertCount(0, $retrievedDraft->blocks);
    }

    public function testConcurrentOperations(): void
    {
        $repository = RepositoryFactory::createInMemoryRepository();

        // Simulate concurrent saves (in real app, this would be different processes)
        $content1 = ContentItem::create('note', 'Content 1');
        $content2 = ContentItem::create('note', 'Content 2');

        $repository->save($content1);
        $repository->save($content2);

        // Both should exist
        $this->assertTrue($repository->exists($content1->id));
        $this->assertTrue($repository->exists($content2->id));
        $this->assertEquals(2, $repository->count());

        // Update one while the other exists
        $updated1 = $content1->withTitle('Updated Content 1');
        $repository->save($updated1);

        // Verify both still exist with correct data
        $retrieved1 = $repository->findById($content1->id);
        $retrieved2 = $repository->findById($content2->id);

        $this->assertEquals('Updated Content 1', $retrieved1->title);
        $this->assertEquals('Content 2', $retrieved2->title);
    }
}
```

2. Run the integration tests:
```bash
./vendor/bin/phpunit --testsuite=Integration
```

### Validation:
- [ ] Integration tests are created
- [ ] Complete workflows are tested
- [ ] Multiple content scenarios are tested
- [ ] All integration tests pass
- [ ] Tests verify end-to-end functionality

---

## Step 4.10: Clean Up and Document
**Time:** 10 minutes

### Instructions:
1. Delete the test file:
```bash
rm test_repository.php
```

2. Update README.md to add repository usage section:

Add this after the "Database Setup" section:

```markdown
## Repository Usage

### Basic Operations

```php
<?php

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Repository\RepositoryFactory;

// Create repository
$repository = RepositoryFactory::getDefaultRepository();

// Create content
$block = MarkdownBlock::create('# Hello World\n\nThis is my content!');
$content = ContentItem::create('note', 'My Note', 'A simple note')
    ->addBlock($block);

// Save content
$repository->save($content);

// Retrieve content
$retrieved = $repository->findById($content->id);

// List all content
$allContent = $repository->findAll(10, 0);

// Update content
$updated = $retrieved->withTitle('Updated Title');
$repository->save($updated);

// Delete content
$repository->delete($content->id);
```

### Repository Features

- **Transaction safety**: All save operations are atomic
- **Cascade deletes**: Deleting content removes associated blocks
- **Pagination support**: `findAll()` supports limit and offset
- **Error handling**: Clear exceptions for database errors
- **Testing support**: In-memory repositories for tests
```

### Validation:
- [ ] Test file is cleaned up
- [ ] README.md includes repository usage
- [ ] Examples are clear and functional
- [ ] Documentation covers key features

---

## Step 4.11: Commit the Changes
**Time:** 5 minutes

### Instructions:
1. Stage all changes:
```bash
git add .
```

2. Commit with descriptive message:
```bash
git commit -m "Implement repository pattern for data access

- Created ContentRepositoryInterface with full CRUD operations
- Implemented SQLiteContentRepository with transaction safety
- Added comprehensive error handling and custom exceptions
- Created RepositoryFactory for easy repository creation
- Added support for in-memory repositories for testing
- Implemented proper object reconstruction from database
- Updated README with repository usage examples

Data access layer is complete and ready for validation layer."
```

3. Push to GitHub:
```bash
git push origin main
```

### Validation:
- [ ] All files are committed
- [ ] Commit message describes the implementation
- [ ] Changes are pushed to GitHub
- [ ] Repository layer is complete

---

## Completion Checklist

### Repository Interface:
- [ ] ContentRepositoryInterface defines all operations
- [ ] Method signatures are clear and consistent
- [ ] Documentation explains each method
- [ ] Interface supports all use cases

### Repository Implementation:
- [ ] SQLiteContentRepository implements interface
- [ ] Transaction safety for all save operations
- [ ] Proper error handling with custom exceptions
- [ ] Object reconstruction from database data
- [ ] Cascade delete functionality

### Factory and Utilities:
- [ ] RepositoryFactory for easy creation
- [ ] Support for file and in-memory databases
- [ ] Singleton pattern for default repository
- [ ] Testing utilities available

### Testing and Validation:
- [ ] All CRUD operations work correctly
- [ ] Transaction integrity maintained
- [ ] Error handling works properly
- [ ] Performance is acceptable

### Documentation:
- [ ] README.md updated with usage examples
- [ ] Code is well-commented
- [ ] Examples are functional and clear

---

## Next Steps

With Task 4 complete, you now have a fully functional data access layer that connects your PHP classes to the SQLite database. The repository pattern provides a clean abstraction that makes testing easy and keeps database concerns separate from business logic.

You're ready to move on to **Task 5: Input Validation**, where you'll implement comprehensive validation for content creation and updates.

## Troubleshooting

### Common Issues:

**Transaction errors:**
- Ensure foreign keys are enabled: `PRAGMA foreign_keys = ON`
- Check that all SQL statements are valid

**Object reconstruction errors:**
- Verify timestamp format is ISO 8601
- Check that all required fields are present

**Performance issues:**
- Ensure indexes are created on frequently queried columns
- Consider lazy loading for large datasets

**Memory issues with large datasets:**
- Use pagination in `findAll()` operations
- Consider streaming for very large result sets
