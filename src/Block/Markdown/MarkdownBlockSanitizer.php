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
        if (isset($blockData['kind'])) {
            $sanitized['kind'] = trim((string) $blockData['kind']);
        }

        // Sanitize source with markdown-specific rules
        if (isset($blockData['source'])) {
            $sanitized['source'] = $this->sanitizeMarkdownSource((string) $blockData['source']);
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
        return $blockType === 'markdown';
    }

    public function getBlockType(): string
    {
        return 'markdown';
    }

    /**
     * Sanitize markdown source content with markdown-specific rules
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
        $source = preg_replace('/\n{3,}/', "\n\n", $source);

        // Normalize heading whitespace (ensure single space after #)
        $source = preg_replace('/^(#{1,6})\s+/', '$1 ', $source);
        $source = preg_replace('/\n(#{1,6})\s+/', "\n$1 ", $source);

        // Normalize list item spacing (ensure single space after bullet/number)
        $source = preg_replace('/^(\s*[-*+])\s+/m', '$1 ', $source);
        $source = preg_replace('/^(\s*\d+\.)\s+/m', '$1 ', $source);

        // Trim the entire content (remove leading/trailing whitespace)
        $source = trim($source);

        return $source;
    }
}
