<?php

declare(strict_types=1);

namespace PortableContent\Tests\Support\Database;

use PDO;
use PDOException;
use RuntimeException;

final class Database
{
    private const DEFAULT_OPTIONS = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    public static function create(string $path): PDO
    {
        try {
            $dsn = "sqlite:{$path}";
            $pdo = new \PDO($dsn, null, null, self::DEFAULT_OPTIONS);

            // Enable foreign key constraints
            $pdo->exec('PRAGMA foreign_keys = ON');

            return $pdo;
        } catch (\PDOException $e) {
            throw new \RuntimeException("Failed to create database connection: {$e->getMessage()}", 0, $e);
        }
    }

    public static function createInMemory(): \PDO
    {
        return self::create(':memory:');
    }

    public static function initialize(string $path): PDO
    {
        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0o755, true)) {
                throw new \RuntimeException("Failed to create directory: {$directory}");
            }
        }

        // Create database connection
        $pdo = self::create($path);

        // Run migrations
        self::runMigrations($pdo);

        return $pdo;
    }

    public static function runMigrations(\PDO $pdo, bool $verbose = true): void
    {
        $migrationsPath = __DIR__.'/../migrations';

        if (!is_dir($migrationsPath)) {
            throw new \RuntimeException("Migrations directory not found: {$migrationsPath}");
        }

        $migrationFiles = glob($migrationsPath.'/*.sql');
        if ($migrationFiles === false) {
            throw new RuntimeException("Failed to read migrations directory: {$migrationsPath}");
        }

        sort($migrationFiles);

        foreach ($migrationFiles as $migrationFile) {
            $sql = file_get_contents($migrationFile);
            if (false === $sql) {
                throw new \RuntimeException("Failed to read migration file: {$migrationFile}");
            }

            try {
                $pdo->exec($sql);
                if ($verbose) {
                    echo 'Applied migration: '.basename($migrationFile)."\n";
                }
            } catch (\PDOException $e) {
                throw new \RuntimeException(
                    'Failed to apply migration '.basename($migrationFile).": {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }
    }

    public static function tableExists(\PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare("
            SELECT name FROM sqlite_master 
            WHERE type='table' AND name=?
        ");
        $stmt->execute([$tableName]);

        return false !== $stmt->fetch();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function getTableInfo(\PDO $pdo, string $tableName): array
    {
        $stmt = $pdo->prepare("PRAGMA table_info({$tableName})");
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
