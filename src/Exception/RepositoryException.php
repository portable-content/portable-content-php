<?php

declare(strict_types=1);

namespace PortableContent\Exception;

class RepositoryException extends \RuntimeException
{
    public static function saveFailure(string $contentId, string $reason): self
    {
        return new self("Failed to save content '{$contentId}': {$reason}");
    }

    public static function deleteFailure(string $contentId, string $reason): self
    {
        return new self("Failed to delete content '{$contentId}': {$reason}");
    }

    public static function queryFailure(string $operation, string $reason): self
    {
        return new self("Failed to execute {$operation}: {$reason}");
    }

    public static function transactionFailure(string $reason): self
    {
        return new self("Transaction failed: {$reason}");
    }
}
