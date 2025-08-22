<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Database;

use PHPUnit\Framework\TestCase;
use PortableContent\Tests\Support\Database\Database;
use PortableContent\Tests\Support\Database\TestDatabaseTrait;

/**
 * @internal
 */
final class DatabaseTest extends TestCase
{
    use TestDatabaseTrait;

    protected function tearDown(): void
    {
        $this->tearDownTestDatabase();
        parent::tearDown();
    }

    public function testCreateInMemoryDatabase(): void
    {
        $pdo = Database::createInMemory();

        $this->assertInstanceOf(\PDO::class, $pdo);

        // Test that foreign keys are enabled
        $stmt = $pdo->query('PRAGMA foreign_keys');
        $this->assertNotFalse($stmt, 'Query should not fail');

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($result, 'Result should be an array');
        $this->assertArrayHasKey('foreign_keys', $result);
        $this->assertEquals('1', $result['foreign_keys']);
    }

    public function testRunMigrations(): void
    {
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false); // Silent for tests

        // Check that tables were created
        $this->assertTrue(Database::tableExists($pdo, 'content_items'));
        $this->assertTrue(Database::tableExists($pdo, 'markdown_blocks'));
    }

    public function testTableExists(): void
    {
        $pdo = $this->createTestDatabase();

        $this->assertTrue(Database::tableExists($pdo, 'content_items'));
        $this->assertTrue(Database::tableExists($pdo, 'markdown_blocks'));
        $this->assertFalse(Database::tableExists($pdo, 'nonexistent_table'));
    }

    public function testGetTableInfo(): void
    {
        $pdo = $this->createTestDatabase();

        $info = Database::getTableInfo($pdo, 'content_items');

        $this->assertIsArray($info);
        $this->assertNotEmpty($info);

        // Check that expected columns exist
        $columnNames = array_column($info, 'name');
        $this->assertContains('id', $columnNames);
        $this->assertContains('type', $columnNames);
        $this->assertContains('title', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function testForeignKeyConstraints(): void
    {
        $pdo = $this->createTestDatabase();

        // Insert a content item
        $this->insertTestContent($pdo, 'test-content', 'note', 'Test Title');

        // Insert a block with valid content_id
        $this->insertTestBlock($pdo, 'test-block', 'test-content', '# Test');

        // This should work
        $this->assertTrue(true);

        // Try to insert a block with invalid content_id - should fail
        $this->expectException(\PDOException::class);
        $this->insertTestBlock($pdo, 'test-block-2', 'nonexistent-content', '# Test');
    }

    public function testCascadeDelete(): void
    {
        $pdo = $this->createTestDatabase();

        // Insert content and block
        $this->insertTestContent($pdo, 'test-content', 'note', 'Test Title');
        $this->insertTestBlock($pdo, 'test-block', 'test-content', '# Test');

        // Verify block exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM markdown_blocks WHERE content_id = ?');
        $stmt->execute(['test-content']);
        $this->assertEquals(1, $stmt->fetchColumn());

        // Delete content
        $stmt = $pdo->prepare('DELETE FROM content_items WHERE id = ?');
        $stmt->execute(['test-content']);

        // Verify block was cascade deleted
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM markdown_blocks WHERE content_id = ?');
        $stmt->execute(['test-content']);
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
