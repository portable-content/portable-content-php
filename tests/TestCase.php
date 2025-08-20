<?php

declare(strict_types=1);

namespace PortableContent\Tests;

use PHPUnit\Framework\TestCase;

abstract class IntegrationTestCase extends TestCase
{
    protected function tearDown(): void
    {
        // Reset any static state
        // TODO: Add RepositoryFactory::resetDefault() when implemented
        parent::tearDown();
    }

    protected function createTestDatabase(): \PDO
    {
        // Create in-memory SQLite database for testing
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // TODO: Add database schema creation when Database class is implemented
        // Database::runMigrations($pdo);

        return $pdo;
    }
}
