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

    /**
     * @param array<int, array<string, mixed>> $blocks
     */
    protected function insertTestContent(\PDO $pdo, string $id, string $type = 'note', ?string $title = null, array $blocks = []): void
    {
        $blocksJson = json_encode($blocks);

        $stmt = $pdo->prepare('
            INSERT INTO content_items (id, type, title, blocks, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?)
        ');
        $stmt->execute([
            $id,
            $type,
            $title,
            $blocksJson,
            '2024-01-01T00:00:00Z',
            '2024-01-01T00:00:00Z',
        ]);
    }

    protected function insertTestContentWithBlock(\PDO $pdo, string $contentId, string $blockId, string $source, string $type = 'note', ?string $title = null): void
    {
        $blocks = [
            [
                'id' => $blockId,
                'type' => 'markdown',
                'source' => $source,
                'created_at' => '2024-01-01T00:00:00Z',
            ],
        ];

        $this->insertTestContent($pdo, $contentId, $type, $title, $blocks);
    }
}
