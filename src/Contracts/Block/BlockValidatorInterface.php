<?php

declare(strict_types=1);

namespace PortableContent\Contracts\Block;

use PortableContent\Validation\ValueObjects\ValidationResult;

interface BlockValidatorInterface
{
    /**
     * Validate block data for a specific block type.
     *
     * @param array<string, mixed> $blockData
     */
    public function validate(array $blockData): ValidationResult;

    /**
     * Check if this validator supports the given block type.
     */
    public function supports(string $blockType): bool;

    /**
     * Get the block type this validator handles.
     */
    public function getBlockType(): string;
}
