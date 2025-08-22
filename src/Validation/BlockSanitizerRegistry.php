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
     * Get all supported block types
     *
     * @return string[]
     */
    public function getSupportedBlockTypes(): array
    {
        return array_keys($this->sanitizers);
    }

    /**
     * Get all registered sanitizers
     *
     * @return BlockSanitizerInterface[]
     */
    public function getAllSanitizers(): array
    {
        return array_values($this->sanitizers);
    }

    /**
     * Sanitize a block using the appropriate sanitizer, or return as-is if no sanitizer exists
     *
     * @param array<string, mixed> $blockData
     * @return array<string, mixed>
     */
    public function sanitizeBlock(array $blockData): array
    {
        $blockType = $blockData['kind'] ?? '';
        
        if (!is_string($blockType) || !$this->hasSanitizer($blockType)) {
            // No specific sanitizer - apply basic sanitization
            return $this->basicBlockSanitization($blockData);
        }

        return $this->getSanitizer($blockType)->sanitize($blockData);
    }

    /**
     * Basic sanitization for blocks without specific sanitizers
     *
     * @param array<string, mixed> $blockData
     * @return array<string, mixed>
     */
    private function basicBlockSanitization(array $blockData): array
    {
        $sanitized = [];

        if (isset($blockData['kind'])) {
            $sanitized['kind'] = trim((string) $blockData['kind']);
        }

        if (isset($blockData['source'])) {
            // Basic sanitization - just ensure it's a string
            $sanitized['source'] = (string) $blockData['source'];
        }

        // Pass through other fields
        foreach ($blockData as $key => $value) {
            if (!isset($sanitized[$key])) {
                $sanitized[$key] = $value;
            }
        }

        return $sanitized;
    }
}
