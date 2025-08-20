<?php

declare(strict_types=1);

namespace PortableContent;

use PortableContent\Contracts\BlockInterface;
use PortableContent\Exception\InvalidContentException;
use Ramsey\Uuid\Uuid;

class ContentItem
{
    /**
     * @param BlockInterface[] $blocks
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $title,
        public readonly ?string $summary,
        public readonly array $blocks,
        public readonly \DateTimeImmutable $createdAt,
        public readonly \DateTimeImmutable $updatedAt,
    ) {}

    /**
     * @param BlockInterface[] $blocks
     */
    public static function create(
        string $type,
        ?string $title = null,
        ?string $summary = null,
        array $blocks = []
    ): self {
        if ('' === trim($type)) {
            throw InvalidContentException::emptyType();
        }

        // Validate all blocks implement BlockInterface
        foreach ($blocks as $block) {
            if (!$block instanceof BlockInterface) {
                throw InvalidContentException::invalidBlockType($block);
            }
        }

        $now = new \DateTimeImmutable();

        return new self(
            id: Uuid::uuid4()->toString(),
            type: trim($type),
            title: $title ? trim($title) : null,
            summary: $summary ? trim($summary) : null,
            blocks: $blocks,
            createdAt: $now,
            updatedAt: $now
        );
    }

    /**
     * @param BlockInterface[] $blocks
     */
    public function withBlocks(array $blocks): self
    {
        // Validate all blocks implement BlockInterface
        foreach ($blocks as $block) {
            if (!$block instanceof BlockInterface) {
                throw InvalidContentException::invalidBlockType($block);
            }
        }

        return new self(
            id: $this->id,
            type: $this->type,
            title: $this->title,
            summary: $this->summary,
            blocks: $blocks,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    public function withTitle(?string $title): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            title: $title,
            summary: $this->summary,
            blocks: $this->blocks,
            createdAt: $this->createdAt,
            updatedAt: new \DateTimeImmutable()
        );
    }

    public function addBlock(BlockInterface $block): self
    {
        $blocks = $this->blocks;
        $blocks[] = $block;

        return $this->withBlocks($blocks);
    }
}
