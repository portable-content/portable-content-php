<?php

declare(strict_types=1);

namespace PortableContent\Validation\ValueObjects;

final class BlockData
{
    public function __construct(
        public readonly string $kind,
        public readonly string $source
    ) {}

    /**
     * @param array{kind: string, source: string} $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            kind: $data['kind'],
            source: $data['source']
        );
    }

    /**
     * @return array{kind: string, source: string}
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'source' => $this->source,
        ];
    }

    /**
     * Check if the block is empty (no meaningful content).
     */
    public function isEmpty(): bool
    {
        return '' === trim($this->source);
    }

    /**
     * Get the length of the source content.
     */
    public function getContentLength(): int
    {
        return strlen($this->source);
    }

    /**
     * Get word count of the source content.
     */
    public function getWordCount(): int
    {
        $trimmed = trim($this->source);
        if ('' === $trimmed) {
            return 0;
        }

        return str_word_count($trimmed);
    }

    /**
     * Get line count of the source content.
     */
    public function getLineCount(): int
    {
        if ('' === $this->source) {
            return 0;
        }

        return substr_count($this->source, "\n") + 1;
    }

    /**
     * Check if this is a markdown block.
     */
    public function isMarkdown(): bool
    {
        return 'markdown' === $this->kind;
    }

    /**
     * Create a new instance with updated source.
     */
    public function withSource(string $source): self
    {
        return new self($this->kind, $source);
    }

    /**
     * Create a new instance with updated kind.
     */
    public function withKind(string $kind): self
    {
        return new self($kind, $this->source);
    }

    /**
     * Create a markdown block.
     */
    public static function markdown(string $source): self
    {
        return new self('markdown', $source);
    }

    /**
     * Check if the source contains specific text.
     */
    public function contains(string $text): bool
    {
        return str_contains($this->source, $text);
    }

    /**
     * Check if the source starts with specific text.
     */
    public function startsWith(string $text): bool
    {
        return str_starts_with($this->source, $text);
    }

    /**
     * Check if the source ends with specific text.
     */
    public function endsWith(string $text): bool
    {
        return str_ends_with($this->source, $text);
    }

    /**
     * Get a preview of the content (first N characters).
     */
    public function getPreview(int $length = 100): string
    {
        if (strlen($this->source) <= $length) {
            return $this->source;
        }

        return substr($this->source, 0, $length).'...';
    }
}
