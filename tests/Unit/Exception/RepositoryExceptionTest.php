<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use PortableContent\Exception\RepositoryException;

/**
 * @internal
 *
 */
final class RepositoryExceptionTest extends TestCase
{
    public function testSaveFailure(): void
    {
        $contentId = 'test-content-id';
        $reason = 'Database connection failed';

        $exception = RepositoryException::saveFailure($contentId, $reason);

        $this->assertInstanceOf(RepositoryException::class, $exception);
        $this->assertEquals("Failed to save content 'test-content-id': Database connection failed", $exception->getMessage());
    }

    public function testDeleteFailure(): void
    {
        $contentId = 'test-content-id';
        $reason = 'Foreign key constraint violation';

        $exception = RepositoryException::deleteFailure($contentId, $reason);

        $this->assertInstanceOf(RepositoryException::class, $exception);
        $this->assertEquals("Failed to delete content 'test-content-id': Foreign key constraint violation", $exception->getMessage());
    }

    public function testQueryFailure(): void
    {
        $operation = 'findAll';
        $reason = 'Table does not exist';

        $exception = RepositoryException::queryFailure($operation, $reason);

        $this->assertInstanceOf(RepositoryException::class, $exception);
        $this->assertEquals('Failed to execute findAll: Table does not exist', $exception->getMessage());
    }

    public function testTransactionFailure(): void
    {
        $reason = 'Deadlock detected';

        $exception = RepositoryException::transactionFailure($reason);

        $this->assertInstanceOf(RepositoryException::class, $exception);
        $this->assertEquals('Transaction failed: Deadlock detected', $exception->getMessage());
    }

    public function testExceptionInheritance(): void
    {
        $exception = RepositoryException::saveFailure('test', 'reason');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionWithEmptyStrings(): void
    {
        $exception = RepositoryException::saveFailure('', '');

        $this->assertEquals("Failed to save content '': ", $exception->getMessage());
    }

    public function testExceptionWithSpecialCharacters(): void
    {
        $contentId = "test'id\"with<special>&chars";
        $reason = "Error with 'quotes' and \"double quotes\"";

        $exception = RepositoryException::saveFailure($contentId, $reason);

        $this->assertStringContainsString($contentId, $exception->getMessage());
        $this->assertStringContainsString($reason, $exception->getMessage());
    }
}
