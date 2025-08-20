# Task 3: Database Schema & Migration - Detailed Steps

## Overview
Create the SQLite database schema that will store ContentItem and MarkdownBlock data. Design a simple migration system and create helper classes for database initialization.

**Estimated Time:** 1 hour  
**Dependencies:** Task 2 (Basic Data Classes) must be completed

---

## Step 3.1: Design the Database Schema
**Time:** 10-15 minutes

### Instructions:
Before writing SQL, let's map our PHP classes to database tables:

**ContentItem → content_items table:**
```
id (TEXT PRIMARY KEY)           ← ContentItem->id
type (TEXT NOT NULL)            ← ContentItem->type  
title (TEXT NULL)               ← ContentItem->title
summary (TEXT NULL)             ← ContentItem->summary
created_at (TEXT NOT NULL)      ← ContentItem->createdAt (ISO 8601)
updated_at (TEXT NOT NULL)      ← ContentItem->updatedAt (ISO 8601)
```

**MarkdownBlock → markdown_blocks table:**
```
id (TEXT PRIMARY KEY)           ← MarkdownBlock->id
content_id (TEXT NOT NULL)      ← Foreign key to content_items.id
source (TEXT NOT NULL)          ← MarkdownBlock->source
created_at (TEXT NOT NULL)      ← MarkdownBlock->createdAt (ISO 8601)
```

### Design Decisions Made:
- **TEXT for UUIDs**: SQLite doesn't have native UUID type
- **TEXT for timestamps**: ISO 8601 strings for portability
- **Foreign key constraint**: Ensures data integrity
- **CASCADE DELETE**: When content is deleted, blocks are too
- **Basic indexes**: For common query patterns

### Validation:
- [ ] Schema maps correctly to PHP classes
- [ ] Relationships are properly defined
- [ ] Data types are appropriate for SQLite

---

## Step 3.2: Create the Migration File
**Time:** 10-15 minutes

### Instructions:
1. Create `migrations/001_create_tables.sql`:

```sql
-- Migration 001: Create initial tables for ContentItem and MarkdownBlock
-- Created: 2024-01-01
-- Description: Basic schema for Phase 1A MVP

-- Enable foreign key constraints
PRAGMA foreign_keys = ON;

-- Create content_items table
CREATE TABLE content_items (
    id TEXT PRIMARY KEY,
    type TEXT NOT NULL DEFAULT 'note',
    title TEXT,
    summary TEXT,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

-- Create markdown_blocks table
CREATE TABLE markdown_blocks (
    id TEXT PRIMARY KEY,
    content_id TEXT NOT NULL,
    source TEXT NOT NULL,
    created_at TEXT NOT NULL,
    FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE
);

-- Create indexes for common queries
CREATE INDEX IF NOT EXISTS idx_content_created ON content_items(created_at DESC);
CREATE INDEX IF NOT EXISTS idx_content_type ON content_items(type);
CREATE INDEX IF NOT EXISTS idx_blocks_content ON markdown_blocks(content_id);

-- Insert a test record to verify schema works
INSERT INTO content_items (id, type, title, created_at, updated_at) 
VALUES ('test-id', 'note', 'Test Content', '2024-01-01T00:00:00Z', '2024-01-01T00:00:00Z');

INSERT INTO markdown_blocks (id, content_id, source, created_at)
VALUES ('test-block-id', 'test-id', '# Test Block', '2024-01-01T00:00:00Z');

-- Verify the foreign key constraint works
SELECT 
    c.id as content_id,
    c.title,
    b.id as block_id,
    b.source
FROM content_items c
JOIN markdown_blocks b ON c.id = b.content_id
WHERE c.id = 'test-id';

-- Clean up test data
DELETE FROM content_items WHERE id = 'test-id';
-- Note: markdown_blocks record should be automatically deleted due to CASCADE
```

### Key Features:
- **PRAGMA foreign_keys**: Ensures constraints are enforced
- **Descriptive comments**: Documents the migration purpose
- **Test data**: Verifies schema works correctly
- **Self-validating**: Includes a test query
- **Cleanup**: Removes test data after verification

### Validation:
- [ ] Migration file is created
- [ ] SQL syntax is correct
- [ ] Foreign key relationships are defined
- [ ] Indexes are created for performance

---

## Step 3.3: Create Database Helper Class
**Time:** 15-20 minutes

### Instructions:
1. Create `src/Database/Database.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Database;

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
            $pdo = new PDO($dsn, null, null, self::DEFAULT_OPTIONS);
            
            // Enable foreign key constraints
            $pdo->exec('PRAGMA foreign_keys = ON');
            
            return $pdo;
        } catch (PDOException $e) {
            throw new RuntimeException("Failed to create database connection: {$e->getMessage()}", 0, $e);
        }
    }

    public static function createInMemory(): PDO
    {
        return self::create(':memory:');
    }

    public static function initialize(string $path): PDO
    {
        // Create directory if it doesn't exist
        $directory = dirname($path);
        if (!is_dir($directory)) {
            if (!mkdir($directory, 0755, true)) {
                throw new RuntimeException("Failed to create directory: {$directory}");
            }
        }

        // Create database connection
        $pdo = self::create($path);

        // Run migrations
        self::runMigrations($pdo);

        return $pdo;
    }

    public static function runMigrations(PDO $pdo): void
    {
        $migrationsPath = __DIR__ . '/../../migrations';
        
        if (!is_dir($migrationsPath)) {
            throw new RuntimeException("Migrations directory not found: {$migrationsPath}");
        }

        $migrationFiles = glob($migrationsPath . '/*.sql');
        sort($migrationFiles);

        foreach ($migrationFiles as $migrationFile) {
            $sql = file_get_contents($migrationFile);
            if ($sql === false) {
                throw new RuntimeException("Failed to read migration file: {$migrationFile}");
            }

            try {
                $pdo->exec($sql);
                echo "Applied migration: " . basename($migrationFile) . "\n";
            } catch (PDOException $e) {
                throw new RuntimeException(
                    "Failed to apply migration " . basename($migrationFile) . ": {$e->getMessage()}",
                    0,
                    $e
                );
            }
        }
    }

    public static function tableExists(PDO $pdo, string $tableName): bool
    {
        $stmt = $pdo->prepare("
            SELECT name FROM sqlite_master 
            WHERE type='table' AND name=?
        ");
        $stmt->execute([$tableName]);
        
        return $stmt->fetch() !== false;
    }

    public static function getTableInfo(PDO $pdo, string $tableName): array
    {
        $stmt = $pdo->prepare("PRAGMA table_info({$tableName})");
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
}
```

### Key Features:
- **Static factory methods**: Easy database creation
- **Error handling**: Proper exception wrapping
- **Migration runner**: Automatically applies SQL files
- **Directory creation**: Handles storage directory setup
- **Utility methods**: Check tables, get schema info

### Validation:
- [ ] Database class is created
- [ ] Can create file and in-memory databases
- [ ] Migration runner works
- [ ] Error handling is comprehensive

---

## Step 3.4: Create CLI Migration Tool
**Time:** 10-15 minutes

### Instructions:
1. Create `bin/migrate.php`:

```php
#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use PortableContent\Database\Database;

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
```

2. Make the script executable:
```bash
chmod +x bin/migrate.php
```

### Validation:
- [ ] CLI script is created and executable
- [ ] Can run migrations with different options
- [ ] Shows helpful usage information
- [ ] Handles errors gracefully

---

## Step 3.5: Test the Database Setup
**Time:** 10-15 minutes

### Instructions:
1. Create the storage directory:
```bash
mkdir -p storage
```

2. Run the migration:
```bash
php bin/migrate.php --info
```

### Expected Output:
```
Initializing database: storage/content.db
Applied migration: 001_create_tables.sql
Database migration completed successfully!

Database Information:
====================

Table: content_items
  id (TEXT) NOT NULL PRIMARY KEY
  type (TEXT) NOT NULL
  title (TEXT) NULL
  summary (TEXT) NULL
  created_at (TEXT) NOT NULL
  updated_at (TEXT) NOT NULL

Table: markdown_blocks
  id (TEXT) NOT NULL PRIMARY KEY
  content_id (TEXT) NOT NULL
  source (TEXT) NOT NULL
  created_at (TEXT) NOT NULL
```

3. Verify the database file exists:
```bash
ls -la storage/
```

4. Test in-memory database:
```bash
php bin/migrate.php --memory --info
```

### Validation:
- [ ] Database file is created in storage/
- [ ] Migration runs without errors
- [ ] Tables are created with correct schema
- [ ] In-memory database works for testing
- [ ] Foreign key constraints are enabled

---

## Step 3.6: Create Simple Database Tests
**Time:** 10 minutes

### Instructions:
1. Create `test_database.php`:

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\Database\Database;

echo "Testing database functionality...\n\n";

// Test 1: Create in-memory database
echo "1. Creating in-memory database:\n";
$pdo = Database::createInMemory();
Database::runMigrations($pdo);
echo "   SUCCESS: In-memory database created\n\n";

// Test 2: Check tables exist
echo "2. Checking tables exist:\n";
$contentTableExists = Database::tableExists($pdo, 'content_items');
$blocksTableExists = Database::tableExists($pdo, 'markdown_blocks');
echo "   content_items table: " . ($contentTableExists ? 'EXISTS' : 'MISSING') . "\n";
echo "   markdown_blocks table: " . ($blocksTableExists ? 'EXISTS' : 'MISSING') . "\n\n";

// Test 3: Insert test data
echo "3. Testing data insertion:\n";
try {
    $pdo->beginTransaction();
    
    // Insert content
    $stmt = $pdo->prepare("
        INSERT INTO content_items (id, type, title, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute(['test-content', 'note', 'Test Note', '2024-01-01T00:00:00Z', '2024-01-01T00:00:00Z']);
    
    // Insert block
    $stmt = $pdo->prepare("
        INSERT INTO markdown_blocks (id, content_id, source, created_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute(['test-block', 'test-content', '# Test Markdown', '2024-01-01T00:00:00Z']);
    
    $pdo->commit();
    echo "   SUCCESS: Test data inserted\n\n";
} catch (Exception $e) {
    $pdo->rollBack();
    echo "   ERROR: {$e->getMessage()}\n\n";
}

// Test 4: Query with JOIN
echo "4. Testing JOIN query:\n";
$stmt = $pdo->query("
    SELECT c.title, b.source 
    FROM content_items c 
    JOIN markdown_blocks b ON c.id = b.content_id
");
$results = $stmt->fetchAll();
foreach ($results as $row) {
    echo "   Content: {$row['title']}, Block: {$row['source']}\n";
}

// Test 5: Test foreign key constraint
echo "\n5. Testing foreign key constraint:\n";
try {
    $stmt = $pdo->prepare("
        INSERT INTO markdown_blocks (id, content_id, source, created_at)
        VALUES (?, ?, ?, ?)
    ");
    $stmt->execute(['orphan-block', 'nonexistent-content', '# Orphan', '2024-01-01T00:00:00Z']);
    echo "   ERROR: Foreign key constraint not working!\n";
} catch (Exception $e) {
    echo "   SUCCESS: Foreign key constraint prevented orphan block\n";
}

echo "\nDatabase tests completed!\n";
```

2. Run the test:
```bash
php test_database.php
```

### Expected Output:
```
Testing database functionality...

1. Creating in-memory database:
Applied migration: 001_create_tables.sql
   SUCCESS: In-memory database created

2. Checking tables exist:
   content_items table: EXISTS
   markdown_blocks table: EXISTS

3. Testing data insertion:
   SUCCESS: Test data inserted

4. Testing JOIN query:
   Content: Test Note, Block: # Test Markdown

5. Testing foreign key constraint:
   SUCCESS: Foreign key constraint prevented orphan block

Database tests completed!
```

### Validation:
- [ ] In-memory database works
- [ ] Tables are created correctly
- [ ] Data insertion works
- [ ] JOIN queries work
- [ ] Foreign key constraints are enforced

---

## Step 3.7: Create Database Tests
**Time:** 10-15 minutes

### Instructions:
1. Create `tests/Unit/Database/DatabaseTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Database;

use PDO;
use PortableContent\Database\Database;
use PortableContent\Tests\TestCase;
use RuntimeException;

final class DatabaseTest extends TestCase
{
    public function testCreateInMemoryDatabase(): void
    {
        $pdo = Database::createInMemory();

        $this->assertInstanceOf(PDO::class, $pdo);

        // Test that foreign keys are enabled
        $stmt = $pdo->query('PRAGMA foreign_keys');
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $this->assertEquals('1', $result['foreign_keys']);
    }

    public function testRunMigrations(): void
    {
        $pdo = Database::createInMemory();
        Database::runMigrations($pdo);

        // Check that tables were created
        $this->assertTrue(Database::tableExists($pdo, 'content_items'));
        $this->assertTrue(Database::tableExists($pdo, 'markdown_blocks'));
    }

    public function testTableExists(): void
    {
        $pdo = $this->createTestDatabase();

        $this->assertTrue(Database::tableExists($pdo, 'content_items'));
        $this->assertTrue(Database::tableExists($pdo, 'markdown_blocks'));
        $this->assertFalse(Database::tableExists($pdo, 'nonexistent_table'));
    }

    public function testGetTableInfo(): void
    {
        $pdo = $this->createTestDatabase();

        $info = Database::getTableInfo($pdo, 'content_items');

        $this->assertIsArray($info);
        $this->assertNotEmpty($info);

        // Check that expected columns exist
        $columnNames = array_column($info, 'name');
        $this->assertContains('id', $columnNames);
        $this->assertContains('type', $columnNames);
        $this->assertContains('title', $columnNames);
        $this->assertContains('created_at', $columnNames);
    }

    public function testForeignKeyConstraints(): void
    {
        $pdo = $this->createTestDatabase();

        // Insert a content item
        $stmt = $pdo->prepare('INSERT INTO content_items (id, type, created_at, updated_at) VALUES (?, ?, ?, ?)');
        $stmt->execute(['test-content', 'note', '2024-01-01T00:00:00Z', '2024-01-01T00:00:00Z']);

        // Insert a block with valid content_id
        $stmt = $pdo->prepare('INSERT INTO markdown_blocks (id, content_id, source, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute(['test-block', 'test-content', '# Test', '2024-01-01T00:00:00Z']);

        // This should work
        $this->assertTrue(true);

        // Try to insert a block with invalid content_id - should fail
        $this->expectException(\PDOException::class);
        $stmt->execute(['test-block-2', 'nonexistent-content', '# Test', '2024-01-01T00:00:00Z']);
    }

    public function testCascadeDelete(): void
    {
        $pdo = $this->createTestDatabase();

        // Insert content and block
        $stmt = $pdo->prepare('INSERT INTO content_items (id, type, created_at, updated_at) VALUES (?, ?, ?, ?)');
        $stmt->execute(['test-content', 'note', '2024-01-01T00:00:00Z', '2024-01-01T00:00:00Z']);

        $stmt = $pdo->prepare('INSERT INTO markdown_blocks (id, content_id, source, created_at) VALUES (?, ?, ?, ?)');
        $stmt->execute(['test-block', 'test-content', '# Test', '2024-01-01T00:00:00Z']);

        // Verify block exists
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM markdown_blocks WHERE content_id = ?');
        $stmt->execute(['test-content']);
        $this->assertEquals(1, $stmt->fetchColumn());

        // Delete content
        $stmt = $pdo->prepare('DELETE FROM content_items WHERE id = ?');
        $stmt->execute(['test-content']);

        // Verify block was cascade deleted
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM markdown_blocks WHERE content_id = ?');
        $stmt->execute(['test-content']);
        $this->assertEquals(0, $stmt->fetchColumn());
    }
}
```

2. Run the database tests:
```bash
./vendor/bin/phpunit --testsuite=Unit tests/Unit/Database/
```

### Validation:
- [ ] Database tests are created
- [ ] All tests pass successfully
- [ ] Foreign key constraints are tested
- [ ] Cascade delete functionality is verified
- [ ] Migration system is tested

---

## Step 3.8: Clean Up and Document
**Time:** 5 minutes

### Instructions:
1. Delete the test file:
```bash
rm test_database.php
```

2. Update `composer.json` to add migration script:
```json
{
    "scripts": {
        "test": "phpunit",
        "test-coverage": "phpunit --coverage-html coverage",
        "migrate": "php bin/migrate.php",
        "migrate-test": "php bin/migrate.php --memory --info"
    }
}
```

3. Update README.md to add database setup section:

Add this after the "Installation" section:

```markdown
## Database Setup

### Initialize Database

```bash
# Create database with migrations
composer migrate

# Or specify custom path
php bin/migrate.php --path=storage/custom.db

# Test with in-memory database
composer migrate-test
```

### Database Schema

The system uses SQLite with two main tables:

- **content_items**: Stores ContentItem metadata (id, type, title, summary, timestamps)
- **markdown_blocks**: Stores MarkdownBlock content (id, content_id, source, timestamp)

Foreign key constraints ensure data integrity between content and blocks.
```

### Validation:
- [ ] Test file is cleaned up
- [ ] Composer scripts are added
- [ ] README.md documents database setup
- [ ] Documentation is clear and helpful

---

## Step 3.9: Commit the Changes
**Time:** 5 minutes

### Instructions:
1. Stage all changes:
```bash
git add .
```

2. Commit with descriptive message:
```bash
git commit -m "Implement database schema and migration system

- Created SQLite schema for content_items and markdown_blocks tables
- Added Database helper class with migration runner
- Created CLI migration tool with options
- Added foreign key constraints and indexes
- Implemented comprehensive error handling
- Updated README with database setup instructions

Database layer is ready for repository implementation."
```

3. Push to GitHub:
```bash
git push origin main
```

### Validation:
- [ ] All files are committed
- [ ] Commit message describes the changes
- [ ] Changes are pushed to GitHub
- [ ] Database setup is documented

---

## Completion Checklist

### Database Schema:
- [ ] SQLite schema created for ContentItem and MarkdownBlock
- [ ] Foreign key constraints properly defined
- [ ] Indexes created for common queries
- [ ] Schema tested with sample data

### Migration System:
- [ ] Migration file with SQL schema
- [ ] Database helper class with initialization
- [ ] CLI migration tool with options
- [ ] Error handling and validation

### Testing:
- [ ] Database creation works
- [ ] Migrations apply correctly
- [ ] Foreign key constraints enforced
- [ ] JOIN queries work properly

### Documentation:
- [ ] README.md updated with database setup
- [ ] Composer scripts for migrations
- [ ] CLI tool usage documented

### Version Control:
- [ ] Changes committed with descriptive message
- [ ] Code pushed to GitHub
- [ ] Ready for repository implementation

---

## Next Steps

With Task 3 complete, you now have a solid database foundation with proper schema, migrations, and helper tools. You're ready to move on to **Task 4: Repository Pattern**, where you'll implement the data access layer that connects your PHP classes to the SQLite database.

## Troubleshooting

### Common Issues:

**SQLite not available:**
- Ensure PHP has SQLite extension: `php -m | grep sqlite`

**Permission errors:**
- Ensure storage directory is writable: `chmod 755 storage/`

**Migration errors:**
- Check SQL syntax in migration files
- Verify file paths are correct

**Foreign key errors:**
- Ensure `PRAGMA foreign_keys = ON` is set
- Check constraint definitions in schema
