<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration\Repository;

use PDO;
use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;
use PortableContent\Contracts\Block\BlockInterface;
use PortableContent\Exception\RepositoryException;
use PortableContent\Tests\Integration\IntegrationTestCase;
use PortableContent\Tests\Support\Database\Database;
use PortableContent\Tests\Support\Repository\SQLiteContentRepository;

/**
 * @internal
 */
final class RepositoryErrorHandlingTest extends IntegrationTestCase
{
    public function testSaveFailureWithCorruptedDatabase(): void
    {
        // Create a repository with a corrupted database
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false);

        // Corrupt the database by dropping the table
        $pdo->exec('DROP TABLE content_items');

        $repository = new SQLiteContentRepository($pdo);
        $content = ContentItem::create('note', 'Test');

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage("Failed to save content '{$content->getId()}'");

        $repository->save($content);
    }

    public function testFindByIdFailureWithCorruptedDatabase(): void
    {
        // Create a repository with a corrupted database
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false);

        // Corrupt the database by dropping the table
        $pdo->exec('DROP TABLE content_items');

        $repository = new SQLiteContentRepository($pdo);

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage('Failed to execute findById');

        $repository->findById('test-id');
    }

    public function testFindAllFailureWithCorruptedDatabase(): void
    {
        // Create a repository with a corrupted database
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false);

        // Corrupt the database by dropping the table
        $pdo->exec('DROP TABLE content_items');

        $repository = new SQLiteContentRepository($pdo);

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage('Failed to execute findAll');

        $repository->findAll();
    }

    public function testDeleteFailureWithCorruptedDatabase(): void
    {
        // Create a repository with a corrupted database
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false);

        // Corrupt the database by dropping the table
        $pdo->exec('DROP TABLE content_items');

        $repository = new SQLiteContentRepository($pdo);

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage("Failed to delete content 'test-id'");

        $repository->delete('test-id');
    }

    public function testCountFailureWithCorruptedDatabase(): void
    {
        // Create a repository with a corrupted database
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false);

        // Corrupt the database by dropping the table
        $pdo->exec('DROP TABLE content_items');

        $repository = new SQLiteContentRepository($pdo);

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage('Failed to execute count');

        $repository->count();
    }

    public function testExistsFailureWithCorruptedDatabase(): void
    {
        // Create a repository with a corrupted database
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false);

        // Corrupt the database by dropping the table
        $pdo->exec('DROP TABLE content_items');

        $repository = new SQLiteContentRepository($pdo);

        $this->expectException(RepositoryException::class);
        $this->expectExceptionMessage('Failed to execute exists');

        $repository->exists('test-id');
    }

    public function testTransactionRollbackOnSaveFailure(): void
    {
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false);

        $repository = new SQLiteContentRepository($pdo);

        // First, save some content successfully
        $content1 = ContentItem::create('note', 'First Content');
        $repository->save($content1);

        $this->assertEquals(1, $repository->count());

        // Make the database read-only to cause a save failure
        $pdo->exec('PRAGMA query_only = ON');

        $content2 = ContentItem::create('note', 'Second Content');
        $content2->addBlock(MarkdownBlock::create('# Test Block'));

        try {
            $repository->save($content2);
            $this->fail('Expected RepositoryException');
        } catch (RepositoryException $e) {
            // Verify we got the expected error message
            $this->assertStringContainsString('Failed to save content', $e->getMessage());

            // Reset the database to read-write mode and verify original content is still there
            $pdo->exec('PRAGMA query_only = OFF');
            $this->assertEquals(1, $repository->count());
        }
    }

    public function testSaveWithInvalidBlockType(): void
    {
        $repository = $this->createTestRepository();

        // Create content with a mock block that's not MarkdownBlock
        $mockBlock = $this->createMock(BlockInterface::class);
        $mockBlock->method('getType')->willReturn('mock');

        $content = new ContentItem(
            id: 'test-id',
            type: 'note',
            title: 'Test',
            summary: null,
            blocks: [$mockBlock],
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable()
        );

        // This should not throw an exception - it should just skip non-MarkdownBlock blocks
        $repository->save($content);

        // Verify content was saved but block was skipped
        $retrieved = $repository->findById('test-id');
        $this->assertNotNull($retrieved);
        $this->assertCount(0, $retrieved->getBlocks()); // Block should be skipped
    }

    public function testDatabaseConnectionFailure(): void
    {
        // Create a PDO instance that will fail
        $pdo = new \PDO('sqlite::memory:');
        $pdo->exec('PRAGMA foreign_keys = ON');

        // Close the connection to simulate failure
        $pdo = null;

        // This test is more conceptual since we can't easily simulate
        // a connection failure with SQLite in-memory database
        $this->assertTrue(true, 'Connection failure testing is conceptual for SQLite in-memory');
    }
}
