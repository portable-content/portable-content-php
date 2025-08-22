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
}
