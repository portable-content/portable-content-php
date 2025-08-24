<?php

declare(strict_types=1);

namespace PortableContent\Block;

use PortableContent\Contracts\Block\BlockInterface;
use Ramsey\Uuid\Uuid;

abstract class AbstractBlock implements BlockInterface
{
    protected string $id;
    protected \DateTimeImmutable $createdAt;

    public function __construct(
        string $id,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = $id;
        $this->createdAt = $createdAt;
    }

    /**
     * Generate a new UUID for block ID.
     */
    protected static function generateId(): string
    {
        return Uuid::uuid4()->toString();
    }

    /**
     * Create a new timestamp for block creation.
     */
    protected static function generateCreatedAt(): \DateTimeImmutable
    {
        return new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set the block ID. Typically used during deserialization from storage.
     */
    public function setId(string $id): void
    {
        $this->id = $id;
    }

    /**
     * Set the creation timestamp. Typically used during deserialization from storage.
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    /**
     * Abstract methods that must be implemented by concrete block types.
     */
    abstract public function isEmpty(): bool;
    abstract public function getWordCount(): int;
    abstract public function getType(): string;
    abstract public function getContent(): string;
}
