<?php

declare(strict_types=1);

namespace PortableContent\Tests\Support\Repository;

use PortableContent\Block\MarkdownBlock;
use PortableContent\ContentItem;
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
        } catch (\PDOException $e) {
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
            if (null === $contentData) {
                return null;
            }

            // Load blocks
            $blocks = $this->loadContentBlocks($id);

            // Reconstruct ContentItem
            return new ContentItem(
                id: $this->ensureString($contentData['id']),
                type: $this->ensureString($contentData['type']),
                title: null !== $contentData['title'] ? $this->ensureString($contentData['title']) : null,
                summary: null !== $contentData['summary'] ? $this->ensureString($contentData['summary']) : null,
                blocks: $blocks,
                createdAt: new \DateTimeImmutable($this->ensureString($contentData['created_at'])),
                updatedAt: new \DateTimeImmutable($this->ensureString($contentData['updated_at']))
            );
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
            // Blocks are automatically deleted due to CASCADE constraint
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
            $content->updatedAt->format('c'),
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
            $block->createdAt->format('c'),
        ]);
    }

    /**
     * @return null|array<string, mixed>
     */
    private function loadContentItem(string $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM content_items WHERE id = ?');
        $stmt->execute([$id]);

        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (false === $result) {
            return null;
        }

        // Ensure we have the expected array structure
        if (!is_array($result)) {
            return null;
        }

        return $result;
    }

    /**
     * @return array<int, MarkdownBlock>
     */
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
                id: $this->ensureString($row['id']),
                source: $this->ensureString($row['source']),
                createdAt: new \DateTimeImmutable($this->ensureString($row['created_at']))
            );
        }

        return $blocks;
    }

    private function ensureString(mixed $value): string
    {
        if (!is_string($value)) {
            throw new \InvalidArgumentException('Expected string value from database');
        }

        return $value;
    }
}
