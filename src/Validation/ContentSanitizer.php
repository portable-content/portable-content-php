<?php

declare(strict_types=1);

namespace PortableContent\Validation;

use PortableContent\Contracts\SanitizerInterface;
use PortableContent\Validation\BlockSanitizerRegistry;

final class ContentSanitizer implements SanitizerInterface
{
    public function __construct(
        private readonly ?BlockSanitizerRegistry $blockSanitizerRegistry = null
    ) {}

    /**
     * Sanitize input data by cleaning and normalizing values.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array
    {
        $sanitized = [];

        // Sanitize type field
        if (isset($data['type']) && $data['type'] !== null) {
            $sanitized['type'] = $this->sanitizeType($data['type']);
        }

        // Sanitize title field
        if (isset($data['title'])) {
            $title = $this->sanitizeTitle($data['title']);
            if ($title !== null) {
                $sanitized['title'] = $title;
            }
        }

        // Sanitize summary field
        if (isset($data['summary'])) {
            $summary = $this->sanitizeSummary($data['summary']);
            if ($summary !== null) {
                $sanitized['summary'] = $summary;
            }
        }

        // Sanitize blocks array
        if (isset($data['blocks']) && $data['blocks'] !== null) {
            $sanitized['blocks'] = $this->sanitizeBlocks($data['blocks']);
        }

        return $sanitized;
    }

    /**
     * Sanitize the type field.
     */
    private function sanitizeType(mixed $type): string
    {
        if (!is_scalar($type) && !is_null($type)) {
            return '';
        }

        $sanitized = trim((string) $type);
        
        // Remove any non-alphanumeric characters except underscores
        $result = preg_replace('/[^a-zA-Z0-9_]/', '', $sanitized);

        return $result !== null ? $result : '';
    }

    /**
     * Sanitize the title field.
     */
    private function sanitizeTitle(mixed $title): ?string
    {
        if (!is_scalar($title) && !is_null($title)) {
            return null;
        }

        if (is_null($title)) {
            return null;
        }

        $sanitized = trim((string) $title);
        
        // Remove control characters but preserve normal whitespace and unicode
        $sanitized = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        
        // Normalize multiple whitespace to single spaces
        $sanitized = preg_replace('/\s+/', ' ', $sanitized ?? '');
        
        return ($sanitized !== null && $sanitized !== '') ? $sanitized : null;
    }

    /**
     * Sanitize the summary field.
     */
    private function sanitizeSummary(mixed $summary): ?string
    {
        if (!is_scalar($summary) && !is_null($summary)) {
            return null;
        }

        if (is_null($summary)) {
            return null;
        }

        $sanitized = trim((string) $summary);
        
        // Remove control characters but preserve normal whitespace and unicode
        $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        $sanitized = $result !== null ? $result : $sanitized;

        // Normalize line endings to Unix style
        $sanitized = str_replace(["\r\n", "\r"], "\n", $sanitized);

        // Limit consecutive newlines to maximum of 2 (paragraph break)
        $result = preg_replace('/\n{3,}/', "\n\n", $sanitized);
        $sanitized = $result !== null ? $result : $sanitized;
        
        return ($sanitized !== null && $sanitized !== '') ? $sanitized : null;
    }

    /**
     * Sanitize the blocks array.
     *
     * @param mixed $blocks
     *
     * @return array<array<string, mixed>>
     */
    private function sanitizeBlocks(mixed $blocks): array
    {
        if (!is_array($blocks)) {
            return [];
        }

        $sanitizedBlocks = [];

        foreach ($blocks as $block) {
            if (!is_array($block)) {
                continue;
            }

            $sanitizedBlock = $this->sanitizeBlock($block);
            if (!empty($sanitizedBlock)) {
                $sanitizedBlocks[] = $sanitizedBlock;
            }
        }

        return $sanitizedBlocks;
    }

    /**
     * Sanitize a single block.
     *
     * @param array<string, mixed> $block
     *
     * @return array<string, mixed>
     */
    private function sanitizeBlock(array $block): array
    {
        $sanitized = [];

        // Sanitize kind field
        if (isset($block['kind'])) {
            $kind = $this->sanitizeBlockKind($block['kind']);
            if ($kind !== '') {
                $sanitized['kind'] = $kind;
            }
        }

        // Sanitize source field - use block sanitizer registry if available
        if (isset($block['source']) && $block['source'] !== null) {
            if ($this->blockSanitizerRegistry !== null && isset($sanitized['kind'])) {
                // Use block-specific sanitizer if available
                $blockData = ['kind' => $sanitized['kind'], 'source' => $block['source']];
                $sanitizedBlockData = $this->blockSanitizerRegistry->sanitizeBlock($blockData);
                if (isset($sanitizedBlockData['source'])) {
                    $sanitized['source'] = $sanitizedBlockData['source'];
                }
            } else {
                // Fallback to basic source sanitization
                $sanitized['source'] = $this->sanitizeBlockSource($block['source']);
            }
        }

        return $sanitized;
    }

    /**
     * Sanitize block kind field.
     */
    private function sanitizeBlockKind(mixed $kind): string
    {
        if (!is_scalar($kind) && !is_null($kind)) {
            return '';
        }

        $sanitized = trim((string) $kind);
        
        // Only allow lowercase alphanumeric characters
        $sanitized = strtolower($sanitized);
        $result = preg_replace('/[^a-z0-9]/', '', $sanitized);

        return $result !== null ? $result : '';
    }

    /**
     * Basic sanitization for block source content.
     */
    private function sanitizeBlockSource(mixed $source): string
    {
        if (!is_scalar($source) && !is_null($source)) {
            return '';
        }

        $sanitized = (string) $source;
        
        // Remove null bytes and other dangerous control characters
        $sanitized = str_replace("\0", '', $sanitized);
        $result = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $sanitized);
        $sanitized = $result !== null ? $result : $sanitized;

        // Normalize line endings to Unix style
        $sanitized = str_replace(["\r\n", "\r"], "\n", $sanitized);

        return $sanitized;
    }

    /**
     * Get sanitization statistics for monitoring.
     *
     * @param array<string, mixed> $originalData
     * @param array<string, mixed> $sanitizedData
     *
     * @return array<string, mixed>
     */
    public function getSanitizationStats(array $originalData, array $sanitizedData): array
    {
        $stats = [
            'fields_processed' => 0,
            'fields_modified' => 0,
            'blocks_processed' => 0,
            'blocks_modified' => 0,
            'total_content_length_before' => 0,
            'total_content_length_after' => 0,
        ];

        // Count processed fields
        foreach (['type', 'title', 'summary'] as $field) {
            if (isset($originalData[$field])) {
                $stats['fields_processed']++;
                $originalValue = $originalData[$field];
                $original = is_scalar($originalValue) ? (string) $originalValue : '';

                $sanitizedValue = $sanitizedData[$field] ?? '';
                $sanitized = is_scalar($sanitizedValue) ? (string) $sanitizedValue : '';
                
                if ($original !== $sanitized) {
                    $stats['fields_modified']++;
                }
                
                $stats['total_content_length_before'] += strlen($original);
                $stats['total_content_length_after'] += strlen($sanitized);
            }
        }

        // Count processed blocks
        if (isset($originalData['blocks']) && is_array($originalData['blocks'])) {
            $stats['blocks_processed'] = count($originalData['blocks']);
            
            if (isset($sanitizedData['blocks']) && is_array($sanitizedData['blocks'])) {
                if (count($originalData['blocks']) !== count($sanitizedData['blocks'])) {
                    $stats['blocks_modified']++;
                }
                
                foreach ($originalData['blocks'] as $index => $originalBlock) {
                    if (isset($sanitizedData['blocks'][$index])) {
                        $originalSource = '';
                        $sanitizedSource = '';

                        if (is_array($originalBlock) && isset($originalBlock['source'])) {
                            $originalSource = (string) $originalBlock['source'];
                        }

                        if (is_array($sanitizedData['blocks'][$index]) && isset($sanitizedData['blocks'][$index]['source'])) {
                            $sanitizedSource = (string) $sanitizedData['blocks'][$index]['source'];
                        }
                        
                        if ($originalSource !== $sanitizedSource) {
                            $stats['blocks_modified']++;
                        }
                        
                        $stats['total_content_length_before'] += strlen($originalSource);
                        $stats['total_content_length_after'] += strlen($sanitizedSource);
                    }
                }
            }
        }

        return $stats;
    }
}
