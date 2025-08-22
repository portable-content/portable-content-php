<?php

declare(strict_types=1);

namespace PortableContent\Block\Markdown;

use PortableContent\Contracts\Block\BlockSanitizerInterface;

final class MarkdownBlockSanitizer implements BlockSanitizerInterface
{
    public function sanitize(array $blockData): array
    {
        $sanitized = [];

        // Sanitize kind field
        if (isset($blockData['kind']) && is_string($blockData['kind'])) {
            $sanitized['kind'] = trim($blockData['kind']);
        }

        // Sanitize source with markdown-specific rules
        if (isset($blockData['source'])) {
            $sourceValue = $blockData['source'];
            if (is_string($sourceValue)) {
                $sanitized['source'] = $this->sanitizeMarkdownSource($sourceValue);
            } elseif (is_scalar($sourceValue) || (is_object($sourceValue) && method_exists($sourceValue, '__toString'))) {
                $sanitized['source'] = $this->sanitizeMarkdownSource((string) $sourceValue);
            } else {
                $sanitized['source'] = '';
            }
        }

        // Pass through any other fields as-is (for extensibility)
        foreach ($blockData as $key => $value) {
            if (!isset($sanitized[$key])) {
                $sanitized[$key] = $value;
            }
        }

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
        // Normalize line endings to Unix style
        $source = str_replace(["\r\n", "\r"], "\n", $source);

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
