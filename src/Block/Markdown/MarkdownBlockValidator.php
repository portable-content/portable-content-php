<?php

declare(strict_types=1);

namespace PortableContent\Block\Markdown;

use PortableContent\Contracts\Block\BlockValidatorInterface;
use PortableContent\Validation\ValueObjects\ValidationResult;

final class MarkdownBlockValidator implements BlockValidatorInterface
{
    private const MAX_CONTENT_LENGTH = 100_000; // 100KB
    private const MIN_CONTENT_LENGTH = 1;

    public function validate(array $blockData): ValidationResult
    {
        $errors = [];

        // Validate required fields
        if (!isset($blockData['kind'])) {
            $errors['kind'][] = 'Block kind is required';
        } elseif ('markdown' !== $blockData['kind']) {
            $errors['kind'][] = 'This validator only handles markdown blocks';
        }

        if (!isset($blockData['source'])) {
            $errors['source'][] = 'Block source is required';
        } else {
            $source = $blockData['source'];

            // Validate source is string
            if (!is_string($source)) {
                $errors['source'][] = 'Block source must be a string';
            } else {
                // Validate content length
                $trimmedSource = trim($source);
                if (strlen($trimmedSource) < self::MIN_CONTENT_LENGTH) {
                    $errors['source'][] = 'Block source cannot be empty after trimming';
                }

                if (strlen($source) > self::MAX_CONTENT_LENGTH) {
                    $errors['source'][] = sprintf(
                        'Block source cannot exceed %d characters (got %d)',
                        self::MAX_CONTENT_LENGTH,
                        strlen($source)
                    );
                }

                // Basic markdown validation (could be extended)
                $this->validateMarkdownSyntax($source, $errors);
            }
        }

        return empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);
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
     * Perform basic markdown syntax validation.
     *
     * @param array<string, string[]> $errors
     */
    private function validateMarkdownSyntax(string $source, array &$errors): void
    {
        // Example validations - could be much more sophisticated

        // Check for balanced code blocks
        $codeBlockCount = substr_count($source, '```');
        if (0 !== $codeBlockCount % 2) {
            $errors['source'][] = 'Unbalanced code blocks (``` markers)';
        }

        // Check for suspicious patterns that might indicate malformed markdown
        if (preg_match('/\[.*\]\((?!https?:\/\/|\/|#).*\)/', $source)) {
            $errors['source'][] = 'Links must use valid URLs or relative paths';
        }

        // Could add more validations:
        // - Image syntax validation
        // - Table syntax validation
        // - Header hierarchy validation
        // - etc.
    }
}
