<?php

declare(strict_types=1);

namespace PortableContent\Contracts;

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
     *
     * @return array<int, ContentItem>
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
