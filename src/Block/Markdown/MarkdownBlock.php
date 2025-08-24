<?php

declare(strict_types=1);

namespace PortableContent\Block\Markdown;

use PortableContent\Block\AbstractBlock;
use PortableContent\Exception\InvalidContentException;

final class MarkdownBlock extends AbstractBlock
{
    private string $source;

    public function __construct(
        string $id,
        string $source,
        \DateTimeImmutable $createdAt,
    ) {
        parent::__construct($id, $createdAt);
        $this->source = $source;
    }

    public static function create(string $source): self
    {
        if ('' === trim($source)) {
            throw InvalidContentException::emptyBlockSource();
        }

        return new self(
            id: self::generateId(),
            source: $source,
            createdAt: self::generateCreatedAt()
        );
    }

    public function getSource(): string
    {
        return $this->source;
    }

    public function setSource(string $source): void
    {
        $this->source = $source;
    }

    public function isEmpty(): bool
    {
        return '' === trim($this->source);
    }

    public function getWordCount(): int
    {
        return str_word_count(strip_tags($this->source));
    }

    public function getType(): string
    {
        return 'markdown';
    }

    public function getContent(): string
    {
        return $this->source;
    }
}
