# Task 2: Basic Data Classes - Detailed Steps

## Overview
Create the core PHP classes (ContentItem and MarkdownBlock) that represent the content entities. These classes form the foundation of the entire system and must be simple, well-designed, and testable.

**Estimated Time:** 1-2 hours  
**Dependencies:** Task 1 (Project Setup) must be completed

---

## Step 2.1: Plan the Data Model
**Time:** 10-15 minutes

### Instructions:
Before writing code, let's clarify exactly what we're building:

**ContentItem represents:**
- A piece of content (like a note or article)
- Contains metadata (id, type, title, summary, timestamps)
- Has one or more blocks of actual content

**MarkdownBlock represents:**
- A single block of markdown content
- Contains the raw markdown source
- Has its own ID and timestamp

### Design Decisions Made:
```php
// ContentItem - the container
- id: string (UUID)
- type: string (e.g., 'note', 'article')
- title: ?string (optional)
- summary: ?string (optional)
- blocks: array (of MarkdownBlock objects)
- createdAt: DateTimeImmutable
- updatedAt: DateTimeImmutable

// MarkdownBlock - the content
- id: string (UUID)
- source: string (the markdown text)
- createdAt: DateTimeImmutable
```

### Validation:
- [ ] Data model is clear and simple
- [ ] Relationships are understood (ContentItem has many MarkdownBlocks)
- [ ] Required vs optional fields are decided

---

## Step 2.2: Create ContentItem Class
**Time:** 15-20 minutes

### Instructions:
1. Create the file `src/ContentItem.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class ContentItem
{
    /**
     * @param MarkdownBlock[] $blocks
     */
    public function __construct(
        public readonly string $id,
        public readonly string $type,
        public readonly ?string $title,
        public readonly ?string $summary,
        public readonly array $blocks,
        public readonly DateTimeImmutable $createdAt,
        public readonly DateTimeImmutable $updatedAt,
    ) {}

    public static function create(
        string $type,
        ?string $title = null,
        ?string $summary = null,
        array $blocks = []
    ): self {
        $now = new DateTimeImmutable();
        
        return new self(
            id: Uuid::uuid4()->toString(),
            type: $type,
            title: $title,
            summary: $summary,
            blocks: $blocks,
            createdAt: $now,
            updatedAt: $now
        );
    }

    public function withBlocks(array $blocks): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            title: $this->title,
            summary: $this->summary,
            blocks: $blocks,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable()
        );
    }

    public function withTitle(?string $title): self
    {
        return new self(
            id: $this->id,
            type: $this->type,
            title: $title,
            summary: $this->summary,
            blocks: $this->blocks,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable()
        );
    }

    public function addBlock(MarkdownBlock $block): self
    {
        $blocks = $this->blocks;
        $blocks[] = $block;
        
        return $this->withBlocks($blocks);
    }
}
```

### Key Design Decisions Explained:
- **`readonly` properties**: Immutable objects are easier to reason about
- **`final` class**: Prevents inheritance complications
- **Static factory method**: `create()` handles UUID generation and timestamps
- **Immutable updates**: `withBlocks()`, `withTitle()` return new instances
- **Type hints**: `@param MarkdownBlock[]` documents the array type

### Validation:
- [ ] File is created at `src/ContentItem.php`
- [ ] Class uses correct namespace
- [ ] All properties are readonly
- [ ] Factory method generates UUIDs
- [ ] Immutable update methods work

---

## Step 2.3: Create MarkdownBlock Class
**Time:** 10-15 minutes

### Instructions:
1. Create the file `src/MarkdownBlock.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class MarkdownBlock
{
    public function __construct(
        public readonly string $id,
        public readonly string $source,
        public readonly DateTimeImmutable $createdAt,
    ) {}

    public static function create(string $source): self
    {
        return new self(
            id: Uuid::uuid4()->toString(),
            source: $source,
            createdAt: new DateTimeImmutable()
        );
    }

    public function withSource(string $source): self
    {
        return new self(
            id: $this->id,
            source: $source,
            createdAt: $this->createdAt
        );
    }

    public function isEmpty(): bool
    {
        return trim($this->source) === '';
    }

    public function getWordCount(): int
    {
        return str_word_count(strip_tags($this->source));
    }
}
```

### Key Design Decisions Explained:
- **Simple structure**: Just ID, source, and timestamp
- **Immutable**: `withSource()` returns new instance
- **Utility methods**: `isEmpty()` and `getWordCount()` for convenience
- **No `updatedAt`**: Blocks are simpler than ContentItems

### Validation:
- [ ] File is created at `src/MarkdownBlock.php`
- [ ] Class uses correct namespace
- [ ] Factory method works
- [ ] Utility methods are helpful
- [ ] Class is immutable

---

## Step 2.4: Test the Classes Manually
**Time:** 10-15 minutes

### Instructions:
1. Create a simple test script `test_classes.php` in the project root:

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;

echo "Testing ContentItem and MarkdownBlock classes...\n\n";

// Test 1: Create a MarkdownBlock
echo "1. Creating MarkdownBlock:\n";
$block = MarkdownBlock::create("# Hello World\n\nThis is my first markdown block!");
echo "   ID: {$block->id}\n";
echo "   Source length: " . strlen($block->source) . " characters\n";
echo "   Word count: {$block->getWordCount()}\n";
echo "   Created: {$block->createdAt->format('Y-m-d H:i:s')}\n\n";

// Test 2: Create a ContentItem
echo "2. Creating ContentItem:\n";
$content = ContentItem::create('note', 'My First Note', 'A simple test note');
echo "   ID: {$content->id}\n";
echo "   Type: {$content->type}\n";
echo "   Title: {$content->title}\n";
echo "   Summary: {$content->summary}\n";
echo "   Blocks: " . count($content->blocks) . "\n";
echo "   Created: {$content->createdAt->format('Y-m-d H:i:s')}\n\n";

// Test 3: Add block to content
echo "3. Adding block to content:\n";
$contentWithBlock = $content->addBlock($block);
echo "   Blocks after adding: " . count($contentWithBlock->blocks) . "\n";
echo "   First block source: " . substr($contentWithBlock->blocks[0]->source, 0, 20) . "...\n\n";

// Test 4: Test immutability
echo "4. Testing immutability:\n";
echo "   Original content blocks: " . count($content->blocks) . "\n";
echo "   Modified content blocks: " . count($contentWithBlock->blocks) . "\n";
echo "   Original unchanged: " . ($content !== $contentWithBlock ? 'YES' : 'NO') . "\n\n";

// Test 5: Test block utilities
echo "5. Testing block utilities:\n";
$emptyBlock = MarkdownBlock::create('   ');
echo "   Empty block is empty: " . ($emptyBlock->isEmpty() ? 'YES' : 'NO') . "\n";
echo "   Regular block is empty: " . ($block->isEmpty() ? 'YES' : 'NO') . "\n\n";

echo "All tests completed successfully!\n";
```

2. Run the test script:
```bash
php test_classes.php
```

### Expected Output:
```
Testing ContentItem and MarkdownBlock classes...

1. Creating MarkdownBlock:
   ID: 12345678-1234-1234-1234-123456789abc
   Source length: 42 characters
   Word count: 8
   Created: 2024-01-01 12:00:00

2. Creating ContentItem:
   ID: 87654321-4321-4321-4321-cba987654321
   Type: note
   Title: My First Note
   Summary: A simple test note
   Blocks: 0
   Created: 2024-01-01 12:00:00

3. Adding block to content:
   Blocks after adding: 1
   First block source: # Hello World...

4. Testing immutability:
   Original content blocks: 0
   Modified content blocks: 1
   Original unchanged: YES

5. Testing block utilities:
   Empty block is empty: YES
   Regular block is empty: NO

All tests completed successfully!
```

### Validation:
- [ ] Script runs without errors
- [ ] UUIDs are generated correctly
- [ ] Timestamps are created
- [ ] Immutability works (original objects unchanged)
- [ ] Utility methods work correctly

---

## Step 2.5: Add Type Safety and Documentation
**Time:** 10-15 minutes

### Instructions:
1. Create `src/Exceptions/InvalidContentException.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Exceptions;

use InvalidArgumentException;

final class InvalidContentException extends InvalidArgumentException
{
    public static function emptyType(): self
    {
        return new self('Content type cannot be empty');
    }

    public static function emptyBlockSource(): self
    {
        return new self('Block source cannot be empty');
    }

    public static function invalidBlockType(mixed $block): self
    {
        $type = get_debug_type($block);
        return new self("Expected MarkdownBlock, got {$type}");
    }
}
```

2. Update `src/ContentItem.php` to add validation:

```php
// Add this use statement at the top
use PortableContent\Exceptions\InvalidContentException;

// Update the create method to add validation
public static function create(
    string $type,
    ?string $title = null,
    ?string $summary = null,
    array $blocks = []
): self {
    if (trim($type) === '') {
        throw InvalidContentException::emptyType();
    }

    // Validate all blocks are MarkdownBlock instances
    foreach ($blocks as $block) {
        if (!$block instanceof MarkdownBlock) {
            throw InvalidContentException::invalidBlockType($block);
        }
    }

    $now = new DateTimeImmutable();
    
    return new self(
        id: Uuid::uuid4()->toString(),
        type: trim($type),
        title: $title ? trim($title) : null,
        summary: $summary ? trim($summary) : null,
        blocks: $blocks,
        createdAt: $now,
        updatedAt: $now
    );
}
```

3. Update `src/MarkdownBlock.php` to add validation:

```php
// Add this use statement at the top
use PortableContent\Exceptions\InvalidContentException;

// Update the create method
public static function create(string $source): self
{
    if (trim($source) === '') {
        throw InvalidContentException::emptyBlockSource();
    }

    return new self(
        id: Uuid::uuid4()->toString(),
        source: $source,
        createdAt: new DateTimeImmutable()
    );
}
```

### Validation:
- [ ] Exception class is created
- [ ] ContentItem validates type and blocks
- [ ] MarkdownBlock validates source
- [ ] Validation throws appropriate exceptions

---

## Step 2.6: Test Error Handling
**Time:** 10 minutes

### Instructions:
1. Create `test_validation.php`:

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Exceptions\InvalidContentException;

echo "Testing validation and error handling...\n\n";

// Test 1: Empty content type
echo "1. Testing empty content type:\n";
try {
    ContentItem::create('');
    echo "   ERROR: Should have thrown exception!\n";
} catch (InvalidContentException $e) {
    echo "   SUCCESS: {$e->getMessage()}\n";
}

// Test 2: Empty block source
echo "\n2. Testing empty block source:\n";
try {
    MarkdownBlock::create('   ');
    echo "   ERROR: Should have thrown exception!\n";
} catch (InvalidContentException $e) {
    echo "   SUCCESS: {$e->getMessage()}\n";
}

// Test 3: Invalid block type
echo "\n3. Testing invalid block type:\n";
try {
    ContentItem::create('note', 'Title', 'Summary', ['not a block']);
    echo "   ERROR: Should have thrown exception!\n";
} catch (InvalidContentException $e) {
    echo "   SUCCESS: {$e->getMessage()}\n";
}

// Test 4: Valid creation still works
echo "\n4. Testing valid creation still works:\n";
try {
    $block = MarkdownBlock::create('# Valid markdown');
    $content = ContentItem::create('note', 'Valid Title', null, [$block]);
    echo "   SUCCESS: Created content with ID {$content->id}\n";
} catch (Exception $e) {
    echo "   ERROR: {$e->getMessage()}\n";
}

echo "\nValidation tests completed!\n";
```

2. Run the validation test:
```bash
php test_validation.php
```

### Expected Output:
```
Testing validation and error handling...

1. Testing empty content type:
   SUCCESS: Content type cannot be empty

2. Testing empty block source:
   SUCCESS: Block source cannot be empty

3. Testing invalid block type:
   SUCCESS: Expected MarkdownBlock, got string

4. Testing valid creation still works:
   SUCCESS: Created content with ID 12345678-1234-1234-1234-123456789abc

Validation tests completed!
```

### Validation:
- [ ] Empty type throws exception
- [ ] Empty source throws exception
- [ ] Invalid block type throws exception
- [ ] Valid creation still works
- [ ] Error messages are clear

---

## Step 2.7: Clean Up and Document
**Time:** 10 minutes

### Instructions:
1. Delete the test files (they were just for verification):
```bash
rm test_classes.php
rm test_validation.php
```

2. Update the main `README.md` to document the classes:

Add this section after the "Installation" section:

```markdown
## Basic Usage

### Creating Content

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;

// Create a markdown block
$block = MarkdownBlock::create('# Hello World\n\nThis is my first note!');

// Create content with the block
$content = ContentItem::create('note', 'My First Note', 'A simple example')
    ->addBlock($block);

echo "Created content: {$content->title}\n";
echo "Block count: " . count($content->blocks) . "\n";
echo "Word count: {$content->blocks[0]->getWordCount()}\n";
```

### Key Features

- **Immutable objects**: All classes are immutable for thread safety
- **Type safety**: Full PHP 8.2 type hints and validation
- **UUID generation**: Automatic unique ID generation
- **Timestamp tracking**: Automatic creation and update timestamps
```

### Validation:
- [ ] Test files are cleaned up
- [ ] README.md includes usage examples
- [ ] Documentation is clear and helpful
- [ ] Examples are copy-paste ready

---

## Step 2.8: Create Unit Tests
**Time:** 15-20 minutes

### Instructions:
1. Create `tests/Unit/ContentItemTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit;

use DateTimeImmutable;
use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Exceptions\InvalidContentException;
use PortableContent\Tests\TestCase;

final class ContentItemTest extends TestCase
{
    public function testCreateWithValidData(): void
    {
        $content = ContentItem::create('note', 'Test Title', 'Test Summary');

        $this->assertNotEmpty($content->id);
        $this->assertEquals('note', $content->type);
        $this->assertEquals('Test Title', $content->title);
        $this->assertEquals('Test Summary', $content->summary);
        $this->assertEmpty($content->blocks);
        $this->assertInstanceOf(DateTimeImmutable::class, $content->createdAt);
        $this->assertInstanceOf(DateTimeImmutable::class, $content->updatedAt);
    }

    public function testCreateWithMinimalData(): void
    {
        $content = ContentItem::create('note');

        $this->assertEquals('note', $content->type);
        $this->assertNull($content->title);
        $this->assertNull($content->summary);
        $this->assertEmpty($content->blocks);
    }

    public function testCreateWithEmptyTypeThrowsException(): void
    {
        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Content type cannot be empty');

        ContentItem::create('');
    }

    public function testCreateWithInvalidBlockTypeThrowsException(): void
    {
        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Expected MarkdownBlock, got string');

        ContentItem::create('note', 'Title', 'Summary', ['not a block']);
    }

    public function testAddBlock(): void
    {
        $content = ContentItem::create('note', 'Test');
        $block = MarkdownBlock::create('# Test Block');

        $updatedContent = $content->addBlock($block);

        $this->assertCount(0, $content->blocks); // Original unchanged
        $this->assertCount(1, $updatedContent->blocks);
        $this->assertSame($block, $updatedContent->blocks[0]);
    }

    public function testWithTitle(): void
    {
        $content = ContentItem::create('note', 'Original Title');
        $updatedContent = $content->withTitle('New Title');

        $this->assertEquals('Original Title', $content->title); // Original unchanged
        $this->assertEquals('New Title', $updatedContent->title);
        $this->assertNotSame($content, $updatedContent);
    }

    public function testWithBlocks(): void
    {
        $content = ContentItem::create('note');
        $block1 = MarkdownBlock::create('# Block 1');
        $block2 = MarkdownBlock::create('# Block 2');

        $updatedContent = $content->withBlocks([$block1, $block2]);

        $this->assertCount(0, $content->blocks); // Original unchanged
        $this->assertCount(2, $updatedContent->blocks);
        $this->assertSame($block1, $updatedContent->blocks[0]);
        $this->assertSame($block2, $updatedContent->blocks[1]);
    }

    public function testImmutability(): void
    {
        $content = ContentItem::create('note', 'Test');
        $block = MarkdownBlock::create('# Test');

        $modified = $content->addBlock($block)->withTitle('New Title');

        // Original should be unchanged
        $this->assertEquals('Test', $content->title);
        $this->assertCount(0, $content->blocks);

        // Modified should have changes
        $this->assertEquals('New Title', $modified->title);
        $this->assertCount(1, $modified->blocks);
    }
}
```

2. Create `tests/Unit/MarkdownBlockTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit;

use DateTimeImmutable;
use PortableContent\MarkdownBlock;
use PortableContent\Exceptions\InvalidContentException;
use PortableContent\Tests\TestCase;

final class MarkdownBlockTest extends TestCase
{
    public function testCreateWithValidSource(): void
    {
        $block = MarkdownBlock::create('# Hello World\n\nThis is a test.');

        $this->assertNotEmpty($block->id);
        $this->assertEquals('# Hello World\n\nThis is a test.', $block->source);
        $this->assertInstanceOf(DateTimeImmutable::class, $block->createdAt);
    }

    public function testCreateWithEmptySourceThrowsException(): void
    {
        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Block source cannot be empty');

        MarkdownBlock::create('   ');
    }

    public function testWithSource(): void
    {
        $block = MarkdownBlock::create('# Original');
        $updatedBlock = $block->withSource('# Updated');

        $this->assertEquals('# Original', $block->source); // Original unchanged
        $this->assertEquals('# Updated', $updatedBlock->source);
        $this->assertEquals($block->id, $updatedBlock->id); // ID preserved
        $this->assertEquals($block->createdAt, $updatedBlock->createdAt); // Timestamp preserved
    }

    public function testIsEmpty(): void
    {
        $emptyBlock = MarkdownBlock::create('   ');
        $nonEmptyBlock = MarkdownBlock::create('# Not Empty');

        $this->assertTrue($emptyBlock->isEmpty());
        $this->assertFalse($nonEmptyBlock->isEmpty());
    }

    public function testGetWordCount(): void
    {
        $block = MarkdownBlock::create('# Hello World\n\nThis is a test with **bold** text.');

        $this->assertEquals(9, $block->getWordCount());
    }

    public function testGetWordCountWithEmptyContent(): void
    {
        $block = MarkdownBlock::create('   ');

        $this->assertEquals(0, $block->getWordCount());
    }

    public function testImmutability(): void
    {
        $original = MarkdownBlock::create('# Original');
        $modified = $original->withSource('# Modified');

        $this->assertNotSame($original, $modified);
        $this->assertEquals('# Original', $original->source);
        $this->assertEquals('# Modified', $modified->source);
    }
}
```

3. Run the tests:
```bash
composer test
```

### Validation:
- [ ] Unit tests are created for both classes
- [ ] All tests pass successfully
- [ ] Tests cover main functionality and edge cases
- [ ] Tests verify immutability behavior
- [ ] Tests check error conditions

---

## Step 2.9: Commit the Changes
**Time:** 5 minutes

### Instructions:
1. Stage all changes:
```bash
git add .
```

2. Commit with descriptive message:
```bash
git commit -m "Implement basic data classes (ContentItem and MarkdownBlock)

- Created ContentItem class with immutable design
- Created MarkdownBlock class with utility methods
- Added comprehensive validation and error handling
- Implemented factory methods with UUID generation
- Added type safety and documentation
- Updated README with usage examples

Classes are fully functional and ready for repository layer."
```

3. Push to GitHub:
```bash
git push origin main
```

### Validation:
- [ ] All files are committed
- [ ] Commit message describes the changes
- [ ] Changes are pushed to GitHub
- [ ] Repository shows the new classes

---

## Completion Checklist

### Core Classes:
- [ ] ContentItem class with all required properties
- [ ] MarkdownBlock class with source and utilities
- [ ] Both classes are immutable and type-safe
- [ ] Factory methods generate UUIDs and timestamps

### Validation:
- [ ] InvalidContentException for error handling
- [ ] Empty type validation
- [ ] Empty source validation
- [ ] Block type validation

### Testing:
- [ ] Manual testing confirms classes work
- [ ] Error handling works correctly
- [ ] Immutability is preserved
- [ ] Utility methods function properly

### Documentation:
- [ ] README.md updated with usage examples
- [ ] Code is well-commented
- [ ] Examples are clear and functional

### Version Control:
- [ ] Changes committed with descriptive message
- [ ] Code pushed to GitHub
- [ ] Repository is ready for next task

---

## Next Steps

With Task 2 complete, you now have solid, well-tested data classes that form the foundation of the system. You're ready to move on to **Task 3: Database Schema & Migration**, where you'll create the SQLite database structure to store these objects.

## Troubleshooting

### Common Issues:

**Autoloading errors:**
- Run `composer dump-autoload` to refresh autoloading

**UUID errors:**
- Ensure `ramsey/uuid` is installed: `composer show ramsey/uuid`

**Namespace errors:**
- Check that namespace matches directory structure: `PortableContent\` → `src/`

**Immutability confusion:**
- Remember: methods like `addBlock()` return NEW objects, they don't modify existing ones
