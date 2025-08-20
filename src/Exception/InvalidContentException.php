<?php

declare(strict_types=1);

namespace PortableContent\Exception;

final class InvalidContentException extends \InvalidArgumentException
{
    public static function emptyType(): self
    {
        return new self('Content type cannot be empty');
    }

    public static function emptyBlockSource(): self
    {
        return new self('Block source cannot be empty');
    }

    public static function invalidBlockType(mixed $block): self
    {
        $type = get_debug_type($block);

        return new self("Expected BlockInterface implementation, got {$type}");
    }
}
