<?php

declare(strict_types=1);

namespace PortableContent\Validation\ValueObjects;

final class ContentCreationRequest
{
    /**
     * @param BlockData[] $blocks
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $title,
        public readonly ?string $summary,
        public readonly array $blocks
    ) {}

    /**
     * Create from validated array data.
     *
     * @param array{type: string, title?: null|string, summary?: null|string, blocks: array<array{kind: string, source: string}>} $data
     */
    public static function fromArray(array $data): self
    {
        $blocks = [];
        foreach ($data['blocks'] as $blockData) {
            $blocks[] = BlockData::fromArray($blockData);
        }

        return new self(
            type: $data['type'],
            title: $data['title'] ?? null,
            summary: $data['summary'] ?? null,
            blocks: $blocks
        );
    }

    /**
     * Convert to array for backward compatibility.
     *
     * @return array{type: string, title: null|string, summary: null|string, blocks: array<array{kind: string, source: string}>}
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'summary' => $this->summary,
            'blocks' => array_map(fn(BlockData $block) => $block->toArray(), $this->blocks),
        ];
    }

    /**
     * Get the number of blocks.
     */
    public function getBlockCount(): int
    {
        return count($this->blocks);
    }

    /**
     * Check if the request has a title.
     */
    public function hasTitle(): bool
    {
        return null !== $this->title && '' !== trim($this->title);
    }

    /**
     * Check if the request has a summary.
     */
    public function hasSummary(): bool
    {
        return null !== $this->summary && '' !== trim($this->summary);
    }

    /**
     * Get blocks of a specific kind.
     *
     * @return BlockData[]
     */
    public function getBlocksByKind(string $kind): array
    {
        return array_filter($this->blocks, fn(BlockData $block) => $block->kind === $kind);
    }

    /**
     * Check if request has blocks of a specific kind.
     */
    public function hasBlocksOfKind(string $kind): bool
    {
        return !empty($this->getBlocksByKind($kind));
    }

    /**
     * Get total content length across all blocks.
     */
    public function getTotalContentLength(): int
    {
        return array_sum(array_map(fn(BlockData $block) => strlen($block->source), $this->blocks));
    }

    /**
     * Check if the request is empty (no meaningful content).
     */
    public function isEmpty(): bool
    {
        if (!$this->hasTitle() && !$this->hasSummary()) {
            return empty($this->blocks) || 0 === $this->getTotalContentLength();
        }

        return false;
    }

    /**
     * Create a new instance with updated title.
     */
    public function withTitle(?string $title): self
    {
        return new self($this->type, $title, $this->summary, $this->blocks);
    }

    /**
     * Create a new instance with updated summary.
     */
    public function withSummary(?string $summary): self
    {
        return new self($this->type, $this->title, $summary, $this->blocks);
    }

    /**
     * Create a new instance with additional block.
     */
    public function withBlock(BlockData $block): self
    {
        $blocks = $this->blocks;
        $blocks[] = $block;

        return new self($this->type, $this->title, $this->summary, $blocks);
    }

    /**
     * Create a new instance with updated blocks.
     *
     * @param BlockData[] $blocks
     */
    public function withBlocks(array $blocks): self
    {
        return new self($this->type, $this->title, $this->summary, $blocks);
    }
}
