<?php

declare(strict_types=1);

namespace PortableContent\Tests\Support\Repository;

use PortableContent\Contracts\ContentRepositoryInterface;
use PortableContent\Tests\Support\Database\Database;

final class RepositoryFactory
{
    private static ?ContentRepositoryInterface $instance = null;

    public static function createSQLiteRepository(string $databasePath): ContentRepositoryInterface
    {
        $pdo = Database::initialize($databasePath);

        return new SQLiteContentRepository($pdo);
    }

    public static function createInMemoryRepository(): ContentRepositoryInterface
    {
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo, false); // Silent for tests

        return new SQLiteContentRepository($pdo);
    }

    public static function getDefaultRepository(): ContentRepositoryInterface
    {
        if (null === self::$instance) {
            self::$instance = self::createSQLiteRepository('storage/content.db');
        }

        return self::$instance;
    }

    public static function setDefaultRepository(ContentRepositoryInterface $repository): void
    {
        self::$instance = $repository;
    }

    public static function resetDefault(): void
    {
        self::$instance = null;
    }

    public static function createWithPDO(\PDO $pdo): ContentRepositoryInterface
    {
        return new SQLiteContentRepository($pdo);
    }
}
