<?php

declare(strict_types=1);

namespace PortableContent\Contracts;

interface BlockInterface
{
    /**
     * Get the unique identifier for this block.
     */
    public function getId(): string;

    /**
     * Get the creation timestamp for this block.
     */
    public function getCreatedAt(): \DateTimeImmutable;

    /**
     * Check if this block is empty (contains no meaningful content).
     */
    public function isEmpty(): bool;

    /**
     * Get the word count for this block's content.
     */
    public function getWordCount(): int;

    /**
     * Get the block type identifier (e.g., 'markdown', 'html', 'code').
     */
    public function getType(): string;

    /**
     * Get the raw content/source of this block.
     */
    public function getContent(): string;
}
