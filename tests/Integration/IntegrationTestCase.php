<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PHPUnit\Framework\TestCase;
use PortableContent\Contracts\ContentRepositoryInterface;
use PortableContent\Tests\Support\Database\Database;
use PortableContent\Tests\Support\Repository\RepositoryFactory;

abstract class IntegrationTestCase extends TestCase
{
    protected function tearDown(): void
    {
        // Reset any static state
        RepositoryFactory::resetDefault();
        parent::tearDown();
    }

    protected function createTestDatabase(): \PDO
    {
        // Create in-memory SQLite database for testing
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false);

        return $pdo;
    }

    protected function createTestRepository(): ContentRepositoryInterface
    {
        return RepositoryFactory::createInMemoryRepository();
    }
}
