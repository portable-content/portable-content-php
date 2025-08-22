<?php

declare(strict_types=1);

namespace PortableContent\Contracts\Block;

interface BlockSanitizerInterface
{
    /**
     * Sanitize block data for a specific block type.
     *
     * @param array<string, mixed> $blockData
     *
     * @return array<string, mixed>
     */
    public function sanitize(array $blockData): array;

    /**
     * Check if this sanitizer supports the given block type.
     */
    public function supports(string $blockType): bool;

    /**
     * Get the block type this sanitizer handles.
     */
    public function getBlockType(): string;
}
