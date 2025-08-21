<?php

declare(strict_types=1);

namespace PortableContent\Exception;

final class ContentNotFoundException extends \RuntimeException
{
    public function __construct(string $contentId)
    {
        parent::__construct("Content with ID '{$contentId}' not found");
    }
}
