<?php

declare(strict_types=1);

namespace PortableContent\Block;

use PortableContent\Contracts\Block\BlockSanitizerInterface;

abstract class AbstractBlockSanitizer implements BlockSanitizerInterface
{
    /**
     * Apply basic sanitization that's common to all block types.
     * 
     * This method handles:
     * - Null byte removal
     * - Control character filtering
     * - Line ending normalization
     * - Basic whitespace cleanup
     * 
     * Subclasses should call this method first, then apply their specific sanitization.
     */
    protected function applyBasicSanitization(string $source): string
    {
        // Remove null bytes and other dangerous control characters
        $sanitized = str_replace("\0", '', $source);
        $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        $sanitized = $result !== null ? $result : $sanitized;
        
        // Normalize line endings to Unix style
        $sanitized = str_replace(["\r\n", "\r"], "\n", $sanitized);
        
        return $sanitized;
    }

    /**
     * Apply basic kind sanitization that's common to all block types.
     * 
     * This method handles:
     * - Trimming whitespace
     * - Converting to lowercase
     * - Removing non-alphanumeric characters
     */
    protected function applyBasicKindSanitization(string $kind): string
    {
        $sanitized = trim($kind);
        $sanitized = strtolower($sanitized);
        $result = preg_replace('/[^a-z0-9]/', '', $sanitized);
        
        return $result !== null ? $result : '';
    }

    /**
     * Validate that required block data fields are present and valid.
     * 
     * @param array<string, mixed> $blockData
     * @throws \InvalidArgumentException if required fields are missing or invalid
     */
    protected function validateBlockData(array $blockData): void
    {
        if (!isset($blockData['kind'])) {
            throw new \InvalidArgumentException('Block data must contain a "kind" field');
        }

        if (!isset($blockData['source'])) {
            throw new \InvalidArgumentException('Block data must contain a "source" field');
        }

        if (!is_string($blockData['kind'])) {
            throw new \InvalidArgumentException('Block "kind" must be a string');
        }

        if (!is_string($blockData['source'])) {
            throw new \InvalidArgumentException('Block "source" must be a string');
        }
    }
}
