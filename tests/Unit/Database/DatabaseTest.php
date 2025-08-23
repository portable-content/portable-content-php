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
        // markdown_blocks table no longer exists - blocks are stored as JSON
    }

    public function testTableExists(): void
    {
        $pdo = $this->createTestDatabase();

        $this->assertTrue(Database::tableExists($pdo, 'content_items'));
        $this->assertFalse(Database::tableExists($pdo, 'markdown_blocks')); // No longer exists
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

    public function testJsonBlockStorage(): void
    {
        $pdo = $this->createTestDatabase();

        // Insert a content item with JSON blocks
        $blocksJson = json_encode([
            [
                'id' => 'test-block',
                'type' => 'markdown',
                'source' => '# Test Block',
                'created_at' => '2024-01-01T00:00:00Z',
            ],
        ]);

        $stmt = $pdo->prepare('
            INSERT INTO content_items (id, type, title, blocks, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            'test-content',
            'note',
            'Test Title',
            $blocksJson,
            '2024-01-01T00:00:00Z',
            '2024-01-01T00:00:00Z',
        ]);

        // Verify JSON data can be retrieved and parsed
        $stmt = $pdo->prepare('SELECT blocks FROM content_items WHERE id = ?');
        $stmt->execute(['test-content']);
        $retrievedJson = $stmt->fetchColumn();

        $this->assertIsString($retrievedJson);
        $blocks = json_decode($retrievedJson, true);
        $this->assertIsArray($blocks);
        $this->assertCount(1, $blocks);
        $this->assertIsArray($blocks[0]);
        $this->assertEquals('test-block', $blocks[0]['id']);
        $this->assertEquals('markdown', $blocks[0]['type']);
        $this->assertEquals('# Test Block', $blocks[0]['source']);
    }

    public function testJsonExtractQueries(): void
    {
        $pdo = $this->createTestDatabase();

        // Insert content with JSON blocks
        $blocksJson = json_encode([
            [
                'id' => 'block-1',
                'type' => 'markdown',
                'source' => '# First Block',
                'created_at' => '2024-01-01T00:00:00Z',
            ],
        ]);

        $stmt = $pdo->prepare('
            INSERT INTO content_items (id, type, title, blocks, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            'test-content',
            'note',
            'Test Title',
            $blocksJson,
            '2024-01-01T00:00:00Z',
            '2024-01-01T00:00:00Z',
        ]);

        // Test JSON extraction
        $stmt = $pdo->prepare('
            SELECT
                json_extract(blocks, "$[0].type") as first_block_type,
                json_extract(blocks, "$[0].source") as first_block_source
            FROM content_items
            WHERE id = ?
        ');
        $stmt->execute(['test-content']);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertIsArray($result);
        $this->assertEquals('markdown', $result['first_block_type']);
        $this->assertEquals('# First Block', $result['first_block_source']);
    }
}
