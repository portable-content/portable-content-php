# Task 2: Basic Data Classes - Detailed Steps

## Overview
Create the core PHP classes (BlockInterface, ContentItem and MarkdownBlock) that represent the content entities. These classes form the foundation of the entire system and must be simple, well-designed, and testable. The BlockInterface ensures extensibility for future block types.

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

**BlockInterface defines:**
- Common contract for all block types (markdown, HTML, code, etc.)
- Standard methods: getId(), getCreatedAt(), isEmpty(), getWordCount(), getType()
- Ensures extensibility for future block implementations

**MarkdownBlock represents:**
- A single block of markdown content implementing BlockInterface
- Contains the raw markdown source
- Has its own ID and timestamp

### Design Decisions Made:
```php
// BlockInterface - the contract
- getId(): string
- getCreatedAt(): DateTimeImmutable
- isEmpty(): bool
- getWordCount(): int
- getType(): string

// ContentItem - the container
- id: string (UUID)
- type: string (e.g., 'note', 'article')
- title: ?string (optional)
- summary: ?string (optional)
- blocks: array (of BlockInterface objects)
- createdAt: DateTimeImmutable
- updatedAt: DateTimeImmutable

// MarkdownBlock - the content (implements BlockInterface)
- id: string (UUID)
- source: string (the markdown text)
- createdAt: DateTimeImmutable
```

### Validation:
- [ ] Data model is clear and simple
- [ ] Relationships are understood (ContentItem has many MarkdownBlocks)
- [ ] Required vs optional fields are decided

---

## Step 2.2: Create BlockInterface
**Time:** 10 minutes

### Instructions:
1. Create the file `src/BlockInterface.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent;

use DateTimeImmutable;

interface BlockInterface
{
    /**
     * Get the unique identifier for this block.
     */
    public function getId(): string;

    /**
     * Get the creation timestamp for this block.
     */
    public function getCreatedAt(): DateTimeImmutable;

    /**
     * Check if this block is empty (contains no meaningful content).
     */
    public function isEmpty(): bool;

    /**
     * Get the word count for this block's content.
     */
    public function getWordCount(): int;

    /**
     * Get the block type identifier (e.g., 'markdown', 'html', 'code').
     */
    public function getType(): string;
}
```

### Key Design Decisions Explained:
- **Interface contract**: Defines common behavior for all block types
- **Extensibility**: Allows for future block types (HTML, code, etc.)
- **Type identification**: `getType()` method for polymorphic handling
- **Standard utilities**: Common methods all blocks should provide

### Validation:
- [ ] File is created at `src/BlockInterface.php`
- [ ] Interface uses correct namespace
- [ ] All methods have proper return types
- [ ] Documentation is clear

---

## Step 2.3: Create ContentItem Class
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
     * @param BlockInterface[] $blocks
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

    public function addBlock(BlockInterface $block): self
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
- **Type hints**: `@param BlockInterface[]` documents the array type

### Validation:
- [ ] File is created at `src/ContentItem.php`
- [ ] Class uses correct namespace
- [ ] All properties are readonly
- [ ] Factory method generates UUIDs
- [ ] Immutable update methods work

---

## Step 2.4: Create MarkdownBlock Class
**Time:** 10-15 minutes

### Instructions:
1. Create the file `src/MarkdownBlock.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent;

use DateTimeImmutable;
use Ramsey\Uuid\Uuid;

final class MarkdownBlock implements BlockInterface
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

    public function getId(): string
    {
        return $this->id;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getWordCount(): int
    {
        return str_word_count(strip_tags($this->source));
    }

    public function getType(): string
    {
        return 'markdown';
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

## Step 2.5: Create Unit Tests
**Time:** 20-25 minutes

### Instructions:
1. Create `tests/Unit/ContentItemTest.php` with comprehensive tests:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PortableContent\ContentItem;
use PortableContent\Exceptions\InvalidContentException;
use PortableContent\MarkdownBlock;

#[CoversClass(ContentItem::class)]
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

    public function testCreateWithEmptyTypeThrowsException(): void
    {
        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Content type cannot be empty');

        ContentItem::create('');
    }

    public function testAddBlock(): void
    {
        $content = ContentItem::create('note', 'Test');
        $block = MarkdownBlock::create('# Test Block');

        $updatedContent = $content->addBlock($block);

        $this->assertCount(0, $content->blocks); // Original unchanged
        $this->assertCount(1, $updatedContent->blocks);
        $this->assertSame($block, $updatedContent->blocks[0]);
        $this->assertNotSame($content, $updatedContent);
    }

    // ... more tests
}
```

2. Create `tests/Unit/MarkdownBlockTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use PortableContent\BlockInterface;
use PortableContent\MarkdownBlock;

#[CoversClass(MarkdownBlock::class)]
final class MarkdownBlockTest extends TestCase
{
    public function testImplementsBlockInterface(): void
    {
        $block = MarkdownBlock::create('# Test');

        $this->assertInstanceOf(BlockInterface::class, $block);
    }

    public function testGetType(): void
    {
        $block = MarkdownBlock::create('# Test');

        $this->assertEquals('markdown', $block->getType());
    }

    // ... more tests
}
```

3. Run the unit tests:
```bash
composer test:unit
```

### Expected Output:
```bash
$ composer test:unit
PHPUnit 11.x.x by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.x

..........................................                        42 / 42 (100%)

Time: 00:00.027, Memory: 10.00 MB

OK, but there were issues!
Tests: 42, Assertions: 102, PHPUnit Warnings: 1.
```

### Validation:
- [ ] All unit tests pass
- [ ] BlockInterface is properly implemented
- [ ] ContentItem works with BlockInterface
- [ ] Immutability is tested and verified
- [ ] Error conditions are properly tested

---

## Step 2.6: Add Type Safety and Documentation
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
        return new self("Expected BlockInterface implementation, got {$type}");
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

    // Validate all blocks implement BlockInterface
    foreach ($blocks as $block) {
        if (!$block instanceof BlockInterface) {
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

## Step 2.7: Create Integration Tests
**Time:** 10 minutes

### Instructions:
1. Create `tests/Integration/ContentCreationTest.php` to test complete workflows:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Tests\Integration\IntegrationTestCase;

#[CoversNothing]
final class ContentCreationTest extends IntegrationTestCase
{
    public function testCompleteContentCreationWorkflow(): void
    {
        // Create multiple blocks
        $titleBlock = MarkdownBlock::create('# My First Note');
        $contentBlock = MarkdownBlock::create('This is the main content.');
        $listBlock = MarkdownBlock::create("## Tasks\n\n- [ ] Task 1\n- [x] Task 2");

        // Create content item and add blocks
        $content = ContentItem::create('note', 'My First Note', 'A test note')
            ->addBlock($titleBlock)
            ->addBlock($contentBlock)
            ->addBlock($listBlock);

        // Verify the complete structure
        $this->assertEquals('note', $content->type);
        $this->assertEquals('My First Note', $content->title);
        $this->assertCount(3, $content->blocks);

        // Test polymorphic behavior through BlockInterface
        foreach ($content->blocks as $block) {
            $this->assertEquals('markdown', $block->getType());
            $this->assertNotEmpty($block->getId());
            $this->assertIsInt($block->getWordCount());
        }
    }
}
```

2. Run the integration tests:
```bash
composer test:integration
```

### Expected Output:
```bash
$ composer test:integration
PHPUnit 11.x.x by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.x

..                                                                  2 / 2 (100%)

Time: 00:00.009, Memory: 10.00 MB

OK, but there were issues!
Tests: 2, Assertions: 31, PHPUnit Warnings: 1.
```

### Validation:
- [ ] Integration tests pass
- [ ] Complete workflows are tested
- [ ] BlockInterface polymorphism works
- [ ] Content creation with multiple blocks works
- [ ] All interface methods are callable

---

## Step 2.8: Update Documentation and Scripts
**Time:** 10 minutes

### Instructions:
1. Update composer.json to add test scripts:
```json
{
    "scripts": {
        "test:unit": "phpunit --testsuite=Unit",
        "test:integration": "phpunit --testsuite=Integration"
    }
}
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

// Create a markdown block (implements BlockInterface)
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
- [ ] Composer scripts are added
- [ ] README.md includes usage examples
- [ ] Documentation mentions BlockInterface
- [ ] Examples show polymorphic usage

---

## Step 2.9: Run All Tests
**Time:** 5 minutes

### Instructions:
1. Run all tests to verify everything works:

```bash
# Run unit tests
composer test:unit

# Run integration tests
composer test:integration

# Run all tests
composer test
```

### Expected Output:
```bash
$ composer test
PHPUnit 11.x.x by Sebastian Bergmann and contributors.

Runtime:       PHP 8.4.x

............................................                      44 / 44 (100%)

Time: 00:00.030, Memory: 10.00 MB

OK, but there were issues!
Tests: 44, Assertions: 133, PHPUnit Warnings: 1.
```

### Validation:
- [ ] All tests pass successfully (44 tests, 133 assertions)
- [ ] Unit tests cover all classes and edge cases
- [ ] Integration tests verify complete workflows
- [ ] BlockInterface polymorphism is tested
- [ ] Both test suites can be run separately

---

## Step 2.10: Commit the Changes
**Time:** 5 minutes

### Instructions:
1. Stage all changes:
```bash
git add .
```

2. Commit with descriptive message:
```bash
git commit -m "Implement basic data classes with BlockInterface

- Created BlockInterface for extensible block system
- Created ContentItem class with immutable design using BlockInterface
- Created MarkdownBlock class implementing BlockInterface
- Added comprehensive validation and error handling
- Implemented factory methods with UUID generation
- Added comprehensive unit and integration tests
- Updated composer scripts for separate test suites
- Updated GitHub Actions for separate unit/integration jobs

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
- [ ] BlockInterface defining contract for all block types
- [ ] ContentItem class working with BlockInterface (not concrete classes)
- [ ] MarkdownBlock class implementing BlockInterface
- [ ] All classes are immutable and type-safe
- [ ] Factory methods generate UUIDs and timestamps

### Validation:
- [ ] InvalidContentException for error handling
- [ ] Empty type validation
- [ ] Empty source validation
- [ ] Block type validation

### Testing:
- [ ] Comprehensive unit tests for all classes
- [ ] Integration tests for complete workflows
- [ ] Separate test suites (unit/integration) with composer scripts
- [ ] GitHub Actions updated for separate test jobs
- [ ] All tests pass (44 tests, 133+ assertions)
- [ ] BlockInterface polymorphism is tested

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
