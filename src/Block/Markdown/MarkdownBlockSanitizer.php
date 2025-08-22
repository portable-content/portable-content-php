<?php

declare(strict_types=1);

namespace PortableContent\Block\Markdown;

use PortableContent\Block\AbstractBlockSanitizer;

final class MarkdownBlockSanitizer extends AbstractBlockSanitizer
{
    public function sanitize(array $blockData): array
    {
        $this->validateBlockData($blockData);

        $sanitized = [];

        // Sanitize kind field using base sanitization (validated as string in validateBlockData)
        $kind = $blockData['kind'];
        assert(is_string($kind));
        $sanitized['kind'] = $this->applyBasicKindSanitization($kind);

        // Sanitize source with markdown-specific rules (validated as string in validateBlockData)
        $source = $blockData['source'];
        assert(is_string($source));
        $sanitized['source'] = $this->sanitizeMarkdownSource($source);

        return $sanitized;
    }

    public function supports(string $blockType): bool
    {
        return 'markdown' === $blockType;
    }

    public function getBlockType(): string
    {
        return 'markdown';
    }

    /**
     * Sanitize markdown source content with markdown-specific rules.
     */
    private function sanitizeMarkdownSource(string $source): string
    {
        // Apply basic sanitization first (null bytes, control chars, line endings)
        $source = $this->applyBasicSanitization($source);

        // Remove trailing whitespace from each line (but preserve line breaks)
        $lines = explode("\n", $source);
        $lines = array_map('rtrim', $lines);
        $source = implode("\n", $lines);

        // Remove excessive blank lines (more than 2 consecutive)
        $result = preg_replace('/\n{3,}/', "\n\n", $source);
        $source = null !== $result ? $result : $source;

        // Normalize heading whitespace (ensure single space after #)
        $result = preg_replace('/^(#{1,6})\s+/', '$1 ', $source);
        $source = null !== $result ? $result : $source;
        $result = preg_replace('/\n(#{1,6})\s+/', "\n$1 ", $source);
        $source = null !== $result ? $result : $source;

        // Normalize list item spacing (ensure single space after bullet/number)
        $result = preg_replace('/^(\s*[-*+])\s+/m', '$1 ', $source);
        $source = null !== $result ? $result : $source;
        $result = preg_replace('/^(\s*\d+\.)\s+/m', '$1 ', $source);
        $source = null !== $result ? $result : $source;

        // Trim the entire content (remove leading/trailing whitespace)
        return trim($source);
    }
}
