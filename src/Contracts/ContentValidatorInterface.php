<?php

declare(strict_types=1);

namespace PortableContent\Contracts;

use PortableContent\Validation\ValueObjects\ValidationResult;

interface ContentValidatorInterface
{
    /**
     * Validate data for content creation.
     *
     * @param array<string, mixed> $data
     */
    public function validateContentCreation(array $data): ValidationResult;

    /**
     * Validate data for content updates (allows partial data).
     *
     * @param array<string, mixed> $data
     */
    public function validateContentUpdate(array $data): ValidationResult;

    /**
     * Validate a single block.
     *
     * @param array<string, mixed> $blockData
     */
    public function validateBlock(array $blockData): ValidationResult;
}
