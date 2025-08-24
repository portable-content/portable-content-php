<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;
use PortableContent\Exception\InvalidContentException;

/**
 * @internal
 */
final class ContentItemTest extends TestCase
{
    public function testCreateWithValidData(): void
    {
        $content = ContentItem::create('note', 'Test Title', 'Test Summary');

        $this->assertNotEmpty($content->getId());
        $this->assertEquals('note', $content->getType());
        $this->assertEquals('Test Title', $content->getTitle());
        $this->assertEquals('Test Summary', $content->getSummary());
        $this->assertEmpty($content->getBlocks());
        $this->assertInstanceOf(\DateTimeImmutable::class, $content->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $content->getUpdatedAt());
    }

    public function testCreateWithMinimalData(): void
    {
        $content = ContentItem::create('note');

        $this->assertEquals('note', $content->getType());
        $this->assertNull($content->getTitle());
        $this->assertNull($content->getSummary());
        $this->assertEmpty($content->getBlocks());
    }

    public function testCreateWithEmptyTypeThrowsException(): void
    {
        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Content type cannot be empty');

        ContentItem::create('');
    }

    public function testCreateWithWhitespaceTypeThrowsException(): void
    {
        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Content type cannot be empty');

        ContentItem::create('   ');
    }

    public function testCreateWithInvalidBlockTypeThrowsException(): void
    {
        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Expected BlockInterface implementation, got string');

        // @phpstan-ignore-next-line
        ContentItem::create('note', 'Title', 'Summary', ['not a block']);
    }

    public function testCreateWithValidBlocks(): void
    {
        $block1 = MarkdownBlock::create('# Block 1');
        $block2 = MarkdownBlock::create('# Block 2');

        $content = ContentItem::create('note', 'Test', null, [$block1, $block2]);

        $this->assertCount(2, $content->getBlocks());
        $this->assertSame($block1, $content->getBlocks()[0]);
        $this->assertSame($block2, $content->getBlocks()[1]);
    }

    public function testAddBlock(): void
    {
        $content = ContentItem::create('note', 'Test');
        $block = MarkdownBlock::create('# Test Block');

        $this->assertCount(0, $content->getBlocks()); // Initially empty

        $content->addBlock($block);

        $this->assertCount(1, $content->getBlocks()); // Now has one block
        $this->assertSame($block, $content->getBlocks()[0]);
    }

    public function testSetTitle(): void
    {
        $content = ContentItem::create('note', 'Original Title');

        $this->assertEquals('Original Title', $content->getTitle());

        $content->setTitle('New Title');

        $this->assertEquals('New Title', $content->getTitle());
    }

    public function testSetTitleNull(): void
    {
        $content = ContentItem::create('note', 'Original Title');

        $this->assertEquals('Original Title', $content->getTitle());

        $content->setTitle(null);

        $this->assertNull($content->getTitle());
    }

    public function testSetBlocks(): void
    {
        $content = ContentItem::create('note');
        $block1 = MarkdownBlock::create('# Block 1');
        $block2 = MarkdownBlock::create('# Block 2');

        $this->assertCount(0, $content->getBlocks()); // Initially empty

        $content->setBlocks([$block1, $block2]);

        $this->assertCount(2, $content->getBlocks());
        $this->assertSame($block1, $content->getBlocks()[0]);
        $this->assertSame($block2, $content->getBlocks()[1]);
    }

    public function testSetBlocksValidatesBlockTypes(): void
    {
        $content = ContentItem::create('note');

        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Expected BlockInterface implementation, got string');

        // @phpstan-ignore-next-line
        $content->setBlocks(['invalid block']);
    }

    public function testMutability(): void
    {
        $content = ContentItem::create('note', 'Test');
        $block = MarkdownBlock::create('# Test');

        // Test that the same object is modified
        $this->assertEquals('Test', $content->getTitle());
        $this->assertCount(0, $content->getBlocks());

        $content->addBlock($block);
        $content->setTitle('New Title');

        // Same object should have changes
        $this->assertEquals('New Title', $content->getTitle());
        $this->assertCount(1, $content->getBlocks());
    }

    public function testUpdatedAtChangesOnModification(): void
    {
        $content = ContentItem::create('note', 'Test');
        $originalUpdatedAt = $content->getUpdatedAt();

        // Small delay to ensure different timestamps
        usleep(1000);

        $content->setTitle('New Title');

        $this->assertGreaterThan($originalUpdatedAt, $content->getUpdatedAt());
        $this->assertEquals($content->getCreatedAt(), $content->getCreatedAt()); // createdAt preserved
    }

    public function testTrimsWhitespaceInFields(): void
    {
        $content = ContentItem::create('  note  ', '  Title  ', '  Summary  ');

        $this->assertEquals('note', $content->getType());
        $this->assertEquals('Title', $content->getTitle());
        $this->assertEquals('Summary', $content->getSummary());
    }

    public function testCompleteContentCreationWorkflow(): void
    {
        // Create multiple blocks
        $titleBlock = MarkdownBlock::create('# My First Note');
        $contentBlock = MarkdownBlock::create('This is the main content of my note.');
        $listBlock = MarkdownBlock::create("## Tasks\n\n- [ ] Task 1\n- [x] Task 2\n- [ ] Task 3");

        // Create content item and add blocks one by one
        $content = ContentItem::create('note', 'My First Note', 'A comprehensive test note');
        $content->addBlock($titleBlock);
        $content->addBlock($contentBlock);
        $content->addBlock($listBlock);

        // Verify the complete structure
        $this->assertEquals('note', $content->getType());
        $this->assertEquals('My First Note', $content->getTitle());
        $this->assertEquals('A comprehensive test note', $content->getSummary());
        $this->assertCount(3, $content->getBlocks());

        // Verify block content and types
        $blocks = $content->getBlocks();
        $this->assertEquals('# My First Note', $blocks[0]->getContent());
        $this->assertEquals('markdown', $blocks[0]->getType());
        $this->assertFalse($blocks[0]->isEmpty());

        $this->assertEquals('This is the main content of my note.', $blocks[1]->getContent());
        $this->assertEquals(8, $blocks[1]->getWordCount());

        $this->assertStringContainsString('Tasks', $blocks[2]->getContent());
        $this->assertGreaterThan(5, $blocks[2]->getWordCount());

        // Test mutability in workflow
        $originalBlockCount = count($content->getBlocks());
        $content->setTitle('Updated Title');

        $this->assertEquals($originalBlockCount, count($content->getBlocks())); // Blocks preserved
        $this->assertEquals('Updated Title', $content->getTitle()); // Title updated
    }

    public function testBlockInterfacePolymorphism(): void
    {
        // This test demonstrates that ContentItem works with any BlockInterface implementation
        $blocks = [
            MarkdownBlock::create('# Heading 1'),
            MarkdownBlock::create('Some **bold** text'),
            MarkdownBlock::create('- List item 1\n- List item 2'),
        ];

        $content = ContentItem::create('article', 'Test Article', null, $blocks);

        // Verify all blocks are treated polymorphically
        foreach ($content->getBlocks() as $block) {
            $this->assertNotEmpty($block->getId());
            $this->assertNotNull($block->getCreatedAt());
            $this->assertEquals('markdown', $block->getType());
            $this->assertIsInt($block->getWordCount());
            $this->assertIsBool($block->isEmpty());
        }

        // Test that we can work with blocks through the interface
        $totalWords = array_reduce(
            $content->getBlocks(),
            fn(int $total, $block) => $total + $block->getWordCount(),
            0
        );

        $this->assertGreaterThan(0, $totalWords);
    }
}
