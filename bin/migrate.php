#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PortableContent\Tests\Support\Database\Database;

function showUsage(): void
{
    echo "Usage: php bin/migrate.php [options]\n";
    echo "\n";
    echo "Options:\n";
    echo "  --path=PATH    Database file path (default: storage/content.db)\n";
    echo "  --memory       Use in-memory database for testing\n";
    echo "  --info         Show database information after migration\n";
    echo "  --help         Show this help message\n";
    echo "\n";
    echo "Examples:\n";
    echo "  php bin/migrate.php\n";
    echo "  php bin/migrate.php --path=storage/test.db\n";
    echo "  php bin/migrate.php --memory --info\n";
}

function main(array $argv): int
{
    $options = getopt('', ['path:', 'memory', 'info', 'help']);
    
    if (isset($options['help'])) {
        showUsage();
        return 0;
    }

    try {
        if (isset($options['memory'])) {
            echo "Creating in-memory database...\n";
            $pdo = Database::createInMemory();
            Database::runMigrations($pdo);
        } else {
            $path = $options['path'] ?? 'storage/content.db';
            echo "Initializing database: {$path}\n";
            $pdo = Database::initialize($path);
        }

        echo "Database migration completed successfully!\n";

        if (isset($options['info'])) {
            echo "\nDatabase Information:\n";
            echo "====================\n";
            
            // Show tables
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table'");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                echo "\nTable: {$table}\n";
                $info = Database::getTableInfo($pdo, $table);
                foreach ($info as $column) {
                    echo "  {$column['name']} ({$column['type']}) " . 
                         ($column['notnull'] ? 'NOT NULL' : 'NULL') . 
                         ($column['pk'] ? ' PRIMARY KEY' : '') . "\n";
                }
            }
        }

        return 0;
    } catch (Exception $e) {
        echo "Error: {$e->getMessage()}\n";
        return 1;
    }
}

exit(main($argv));
