<?php

declare(strict_types=1);

namespace PortableContent\Validation;

use PortableContent\Contracts\Block\BlockSanitizerInterface;

final class BlockSanitizerRegistry
{
    /**
     * @var array<string, BlockSanitizerInterface>
     */
    private array $sanitizers = [];

    /**
     * @param BlockSanitizerInterface[] $sanitizers
     */
    public function __construct(array $sanitizers = [])
    {
        foreach ($sanitizers as $sanitizer) {
            $this->register($sanitizer);
        }
    }

    public function register(BlockSanitizerInterface $sanitizer): void
    {
        $blockType = $sanitizer->getBlockType();

        if (isset($this->sanitizers[$blockType])) {
            throw new \InvalidArgumentException(
                "Block sanitizer for type '{$blockType}' is already registered"
            );
        }

        $this->sanitizers[$blockType] = $sanitizer;
    }

    public function getSanitizer(string $blockType): ?BlockSanitizerInterface
    {
        return $this->sanitizers[$blockType] ?? null;
    }

    public function hasSanitizer(string $blockType): bool
    {
        return isset($this->sanitizers[$blockType]);
    }

    /**
     * Get all supported block types.
     *
     * @return string[]
     */
    public function getSupportedBlockTypes(): array
    {
        return array_keys($this->sanitizers);
    }

    /**
     * Get all registered sanitizers.
     *
     * @return BlockSanitizerInterface[]
     */
    public function getAllSanitizers(): array
    {
        return array_values($this->sanitizers);
    }

    /**
     * Sanitize multiple blocks using the appropriate sanitizers.
     *
     * @param array<array<string, mixed>> $blocks Array of block data arrays
     *
     * @return array<array<string, mixed>> Array of sanitized block data arrays
     *
     * @throws \InvalidArgumentException if a block type has no registered sanitizer
     */
    public function sanitizeBlocks(array $blocks): array
    {
        $sanitizedBlocks = [];

        foreach ($blocks as $blockData) {
            if (!is_array($blockData)) {
                continue; // Skip invalid block data
            }

            $sanitizedBlock = $this->sanitizeBlock($blockData);
            if (!empty($sanitizedBlock)) {
                $sanitizedBlocks[] = $sanitizedBlock;
            }
        }

        return $sanitizedBlocks;
    }

    /**
     * Sanitize a single block using the appropriate sanitizer.
     *
     * Block data structure:
     * - 'kind' (string, required): The block type (e.g., 'markdown', 'html', 'code')
     * - 'source' (string, required): The raw content of the block
     * - Additional fields may be present and will be passed through to the sanitizer
     *
     * Return structure:
     * - 'kind' (string): The sanitized block type (normalized, lowercase)
     * - 'source' (string): The sanitized block content
     * - Additional fields may be returned depending on the specific sanitizer
     *
     * @param array<string, mixed> $blockData Block data containing 'kind' and 'source' fields
     *
     * @return array<string, mixed> Sanitized block data with same structure
     *
     * @throws \InvalidArgumentException if the block type has no registered sanitizer
     */
    public function sanitizeBlock(array $blockData): array
    {
        $blockType = $blockData['kind'] ?? '';

        if (!is_string($blockType)) {
            throw new \InvalidArgumentException('Block data must contain a valid "kind" field');
        }

        if (!$this->hasSanitizer($blockType)) {
            throw new \InvalidArgumentException("No sanitizer registered for block type: {$blockType}");
        }

        $sanitizer = $this->getSanitizer($blockType);
        if ($sanitizer === null) {
            throw new \InvalidArgumentException("No sanitizer registered for block type: {$blockType}");
        }

        return $sanitizer->sanitize($blockData);
    }


}
