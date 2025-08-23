<?php

declare(strict_types=1);

namespace PortableContent\Contracts;

interface SanitizerInterface
{
    /**
     * Sanitize input data by cleaning and normalizing values.
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    public function sanitize(array $data): array;

    /**
     * Get statistics about the sanitization process.
     *
     * @param array<string, mixed> $originalData  Original input data
     * @param array<string, mixed> $sanitizedData Sanitized output data
     *
     * @return array<string, mixed> Statistics about the sanitization process
     */
    public function getSanitizationStats(array $originalData, array $sanitizedData): array;
}
