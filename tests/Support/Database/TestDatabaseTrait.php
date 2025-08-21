<?php

declare(strict_types=1);

namespace PortableContent\Tests\Support\Database;

trait TestDatabaseTrait
{
    private ?\PDO $testDatabase = null;

    protected function createTestDatabase(): \PDO
    {
        if (null === $this->testDatabase) {
            $this->testDatabase = Database::createInMemory();
            Database::runMigrations($this->testDatabase, false); // Silent for tests
        }

        return $this->testDatabase;
    }

    protected function tearDownTestDatabase(): void
    {
        $this->testDatabase = null;
    }

    protected function insertTestContent(\PDO $pdo, string $id, string $type = 'note', ?string $title = null): void
    {
        $stmt = $pdo->prepare('
            INSERT INTO content_items (id, type, title, created_at, updated_at) 
            VALUES (?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $type,
            $title,
            '2024-01-01T00:00:00Z',
            '2024-01-01T00:00:00Z',
        ]);
    }

    protected function insertTestBlock(\PDO $pdo, string $id, string $contentId, string $source): void
    {
        $stmt = $pdo->prepare('
            INSERT INTO markdown_blocks (id, content_id, source, created_at) 
            VALUES (?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $contentId,
            $source,
            '2024-01-01T00:00:00Z',
        ]);
    }
}
