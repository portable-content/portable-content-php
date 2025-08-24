<?php

declare(strict_types=1);

namespace PortableContent;

use PortableContent\Contracts\Block\BlockInterface;
use PortableContent\Exception\InvalidContentException;
use Ramsey\Uuid\Uuid;

class ContentItem
{
    private string $id;
    private string $type;
    private ?string $title;
    private ?string $summary;
    /** @var BlockInterface[] */
    private array $blocks;
    private \DateTimeImmutable $createdAt;
    private \DateTimeImmutable $updatedAt;

    /**
     * @param BlockInterface[] $blocks
     */
    public function __construct(
        string $id,
        string $type,
        ?string $title,
        ?string $summary,
        array $blocks,
        \DateTimeImmutable $createdAt,
        \DateTimeImmutable $updatedAt,
    ) {
        $this->id = $id;
        $this->type = $type;
        $this->title = $title;
        $this->summary = $summary;
        $this->blocks = $blocks;
        $this->createdAt = $createdAt;
        $this->updatedAt = $updatedAt;
    }

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
            Uuid::uuid4()->toString(),
            trim($type),
            $title ? trim($title) : null,
            $summary ? trim($summary) : null,
            $blocks,
            $now,
            $now
        );
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): void
    {
        if ('' === trim($type)) {
            throw InvalidContentException::emptyType();
        }
        $this->type = trim($type);
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title ? trim($title) : null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getSummary(): ?string
    {
        return $this->summary;
    }

    public function setSummary(?string $summary): void
    {
        $this->summary = $summary ? trim($summary) : null;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * @return BlockInterface[]
     */
    public function getBlocks(): array
    {
        return $this->blocks;
    }

    /**
     * @param BlockInterface[] $blocks
     */
    public function setBlocks(array $blocks): void
    {
        // Validate all blocks implement BlockInterface
        foreach ($blocks as $block) {
            if (!$block instanceof BlockInterface) {
                throw InvalidContentException::invalidBlockType($block);
            }
        }
        $this->blocks = $blocks;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function addBlock(BlockInterface $block): void
    {
        $this->blocks[] = $block;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }


}
