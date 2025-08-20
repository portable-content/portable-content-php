<?php

declare(strict_types=1);

namespace PortableContent\Block;

use PortableContent\Contracts\BlockInterface;
use PortableContent\Exception\InvalidContentException;
use Ramsey\Uuid\Uuid;

final class MarkdownBlock implements BlockInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly \DateTimeImmutable $createdAt,
    ) {}

    public static function create(string $source): self
    {
        if ('' === trim($source)) {
            throw InvalidContentException::emptyBlockSource();
        }

        return new self(
            id: Uuid::uuid4()->toString(),
            source: $source,
            createdAt: new \DateTimeImmutable()
        );
    }

    public function withSource(string $source): self
    {
        return new self(
            id: $this->id,
            source: $source,
            createdAt: $this->createdAt
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
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
