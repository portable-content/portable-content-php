<?php

declare(strict_types=1);

namespace PortableContent\Tests\Support\Repository;

use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;
use PortableContent\Contracts\Block\BlockInterface;
use PortableContent\Contracts\ContentRepositoryInterface;
use PortableContent\Exception\RepositoryException;

final class SQLiteContentRepository implements ContentRepositoryInterface
{
    public function __construct(private \PDO $pdo)
    {
        // Ensure foreign keys are enabled
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    public function save(ContentItem $content): void
    {
        try {
            $this->pdo->beginTransaction();

            $blocksJson = json_encode($this->serializeBlocks($content->getBlocks()), JSON_THROW_ON_ERROR);

            $stmt = $this->pdo->prepare('
                INSERT OR REPLACE INTO content_items
                (id, type, title, summary, blocks, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ');

            $stmt->execute([
                $content->getId(),
                $content->getType(),
                $content->getTitle(),
                $content->getSummary(),
                $blocksJson,
                $content->getCreatedAt()->format('c'),
                $content->getUpdatedAt()->format('c'),
            ]);

            $this->pdo->commit();
        } catch (\PDOException $e) {
            $this->pdo->rollBack();

            throw RepositoryException::saveFailure($content->getId(), $e->getMessage());
        } catch (\Exception $e) {
            $this->pdo->rollBack();

            throw RepositoryException::transactionFailure($e->getMessage());
        }
    }

    public function findById(string $id): ?ContentItem
    {
        try {
            $stmt = $this->pdo->prepare('SELECT * FROM content_items WHERE id = ?');
            $stmt->execute([$id]);
            $data = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!is_array($data)) {
                return null;
            }

            return $this->hydrateContentItem($data);
        } catch (\PDOException $e) {
            throw RepositoryException::queryFailure('findById', $e->getMessage());
        }
    }

    /**
     * @return array<int, ContentItem>
     */
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
                if (null !== $content) {
                    $results[] = $content;
                }
            }

            return $results;
        } catch (\PDOException $e) {
            throw RepositoryException::queryFailure('findAll', $e->getMessage());
        }
    }

    public function delete(string $id): void
    {
        try {
            $stmt = $this->pdo->prepare('DELETE FROM content_items WHERE id = ?');
            $stmt->execute([$id]);
            // Blocks are stored as JSON, so they're automatically deleted with the content item
        } catch (\PDOException $e) {
            throw RepositoryException::deleteFailure($id, $e->getMessage());
        }
    }

    public function count(): int
    {
        try {
            $stmt = $this->pdo->query('SELECT COUNT(*) FROM content_items');
            if (false === $stmt) {
                throw new \PDOException('Failed to execute count query');
            }
            $result = $stmt->fetchColumn();

            return (int) $result;
        } catch (\PDOException $e) {
            throw RepositoryException::queryFailure('count', $e->getMessage());
        }
    }

    public function exists(string $id): bool
    {
        try {
            $stmt = $this->pdo->prepare('SELECT 1 FROM content_items WHERE id = ? LIMIT 1');
            $stmt->execute([$id]);

            return false !== $stmt->fetch();
        } catch (\PDOException $e) {
            throw RepositoryException::queryFailure('exists', $e->getMessage());
        }
    }

    public function findByType(string $type, int $limit = 20, int $offset = 0): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM content_items
                WHERE type = ?
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ');
            $stmt->execute([$type, $limit, $offset]);
            $contentRows = $stmt->fetchAll();

            $results = [];
            foreach ($contentRows as $row) {
                $content = $this->hydrateContentItem($row);
                $results[] = $content;
            }

            return $results;
        } catch (\PDOException $e) {
            throw RepositoryException::queryFailure('findByType', $e->getMessage());
        }
    }

    public function findByDateRange(\DateTimeInterface $start, \DateTimeInterface $end): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM content_items
                WHERE created_at BETWEEN ? AND ?
                ORDER BY created_at DESC
            ');
            $stmt->execute([$start->format('c'), $end->format('c')]);
            $contentRows = $stmt->fetchAll();

            $results = [];
            foreach ($contentRows as $row) {
                $content = $this->hydrateContentItem($row);
                $results[] = $content;
            }

            return $results;
        } catch (\PDOException $e) {
            throw RepositoryException::queryFailure('findByDateRange', $e->getMessage());
        }
    }

    public function search(string $query, int $limit = 10): array
    {
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM content_items
                WHERE title LIKE ? OR summary LIKE ? OR blocks LIKE ?
                ORDER BY created_at DESC
                LIMIT ?
            ');
            $searchTerm = "%{$query}%";
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $limit]);
            $contentRows = $stmt->fetchAll();

            $results = [];
            foreach ($contentRows as $row) {
                $content = $this->hydrateContentItem($row);
                $results[] = $content;
            }

            return $results;
        } catch (\PDOException $e) {
            throw RepositoryException::queryFailure('search', $e->getMessage());
        }
    }

    public function findSimilar(ContentItem $content, int $limit = 10): array
    {
        // For SQLite, we'll use a simple approach: find content of the same type
        // excluding the original content
        try {
            $stmt = $this->pdo->prepare('
                SELECT * FROM content_items
                WHERE type = ? AND id != ?
                ORDER BY created_at DESC
                LIMIT ?
            ');
            $stmt->execute([$content->getType(), $content->getId(), $limit]);
            $contentRows = $stmt->fetchAll();

            $results = [];
            foreach ($contentRows as $row) {
                $contentItem = $this->hydrateContentItem($row);
                $results[] = $contentItem;
            }

            return $results;
        } catch (\PDOException $e) {
            throw RepositoryException::queryFailure('findSimilar', $e->getMessage());
        }
    }

    public function getCapabilities(): array
    {
        return [
            'crud',
            'full_text_search',
            'transactions',
        ];
    }

    public function supports(string $capability): bool
    {
        return in_array($capability, $this->getCapabilities(), true);
    }

    /**
     * @return array<int, MarkdownBlock>
     */
    private function deserializeBlocks(string $blocksJson): array
    {
        $blocksData = json_decode($blocksJson, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($blocksData)) {
            return [];
        }

        $blocks = [];

        foreach ($blocksData as $blockData) {
            if (!is_array($blockData) || !isset($blockData['type'])) {
                continue;
            }

            if ('markdown' === $blockData['type']) {
                $blocks[] = new MarkdownBlock(
                    id: $this->ensureString($blockData['id'] ?? ''),
                    source: $this->ensureString($blockData['source'] ?? ''),
                    createdAt: new \DateTimeImmutable($this->ensureString($blockData['created_at'] ?? ''))
                );
            }
        }

        return $blocks;
    }

    /**
     * @param array<string, mixed> $data
     */
    private function hydrateContentItem(array $data): ContentItem
    {
        $blocksData = $data['blocks'] ?? '[]';
        if (!is_string($blocksData)) {
            $blocksData = '[]';
        }
        $blocks = $this->deserializeBlocks($blocksData);

        return new ContentItem(
            id: $this->ensureString($data['id']),
            type: $this->ensureString($data['type']),
            title: null !== $data['title'] ? $this->ensureString($data['title']) : null,
            summary: null !== $data['summary'] ? $this->ensureString($data['summary']) : null,
            blocks: $blocks,
            createdAt: new \DateTimeImmutable($this->ensureString($data['created_at'])),
            updatedAt: new \DateTimeImmutable($this->ensureString($data['updated_at']))
        );
    }

    /**
     * @param array<int, BlockInterface> $blocks
     *
     * @return array<int, array<string, string>>
     */
    private function serializeBlocks(array $blocks): array
    {
        $serialized = [];
        foreach ($blocks as $block) {
            if ($block instanceof MarkdownBlock) {
                $serialized[] = [
                    'id' => $block->getId(),
                    'type' => 'markdown',
                    'source' => $block->getSource(),
                    'created_at' => $block->getCreatedAt()->format('c'),
                ];
            }
        }

        return $serialized;
    }

    private function ensureString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Expected string value from database');
        }

        return $value;
    }
}
