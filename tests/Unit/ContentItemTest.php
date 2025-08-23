<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;
use PortableContent\Exception\InvalidContentException;

/**
 * @internal
 *
 */
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
        $this->assertInstanceOf(\DateTimeImmutable::class, $content->createdAt);
        $this->assertInstanceOf(\DateTimeImmutable::class, $content->updatedAt);
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

        $this->assertCount(2, $content->blocks);
        $this->assertSame($block1, $content->blocks[0]);
        $this->assertSame($block2, $content->blocks[1]);
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

    public function testWithTitle(): void
    {
        $content = ContentItem::create('note', 'Original Title');
        $updatedContent = $content->withTitle('New Title');

        $this->assertEquals('Original Title', $content->title); // Original unchanged
        $this->assertEquals('New Title', $updatedContent->title);
        $this->assertNotSame($content, $updatedContent);
    }

    public function testWithTitleNull(): void
    {
        $content = ContentItem::create('note', 'Original Title');
        $updatedContent = $content->withTitle(null);

        $this->assertEquals('Original Title', $content->title); // Original unchanged
        $this->assertNull($updatedContent->title);
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
        $this->assertNotSame($content, $updatedContent);
    }

    public function testWithBlocksValidatesBlockTypes(): void
    {
        $content = ContentItem::create('note');

        $this->expectException(InvalidContentException::class);
        $this->expectExceptionMessage('Expected BlockInterface implementation, got string');

        // @phpstan-ignore-next-line
        $content->withBlocks(['invalid block']);
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

    public function testUpdatedAtChangesOnModification(): void
    {
        $content = ContentItem::create('note', 'Test');

        // Small delay to ensure different timestamps
        usleep(1000);

        $modified = $content->withTitle('New Title');

        $this->assertGreaterThan($content->updatedAt, $modified->updatedAt);
        $this->assertEquals($content->createdAt, $modified->createdAt); // createdAt preserved
    }

    public function testTrimsWhitespaceInFields(): void
    {
        $content = ContentItem::create('  note  ', '  Title  ', '  Summary  ');

        $this->assertEquals('note', $content->type);
        $this->assertEquals('Title', $content->title);
        $this->assertEquals('Summary', $content->summary);
    }

    public function testCompleteContentCreationWorkflow(): void
    {
        // Create multiple blocks
        $titleBlock = MarkdownBlock::create('# My First Note');
        $contentBlock = MarkdownBlock::create('This is the main content of my note.');
        $listBlock = MarkdownBlock::create("## Tasks\n\n- [ ] Task 1\n- [x] Task 2\n- [ ] Task 3");

        // Create content item and add blocks one by one
        $content = ContentItem::create('note', 'My First Note', 'A comprehensive test note')
            ->addBlock($titleBlock)
            ->addBlock($contentBlock)
            ->addBlock($listBlock)
        ;

        // Verify the complete structure
        $this->assertEquals('note', $content->type);
        $this->assertEquals('My First Note', $content->title);
        $this->assertEquals('A comprehensive test note', $content->summary);
        $this->assertCount(3, $content->blocks);

        // Verify block content and types
        $this->assertEquals('# My First Note', $content->blocks[0]->getContent());
        $this->assertEquals('markdown', $content->blocks[0]->getType());
        $this->assertFalse($content->blocks[0]->isEmpty());

        $this->assertEquals('This is the main content of my note.', $content->blocks[1]->getContent());
        $this->assertEquals(8, $content->blocks[1]->getWordCount());

        $this->assertStringContainsString('Tasks', $content->blocks[2]->getContent());
        $this->assertGreaterThan(5, $content->blocks[2]->getWordCount());

        // Test immutability in workflow
        $originalBlockCount = count($content->blocks);
        $newContent = $content->withTitle('Updated Title');

        $this->assertEquals($originalBlockCount, count($content->blocks)); // Original unchanged
        $this->assertEquals('My First Note', $content->title); // Original title unchanged
        $this->assertEquals('Updated Title', $newContent->title); // New title applied
        $this->assertEquals($originalBlockCount, count($newContent->blocks)); // Blocks preserved
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
        foreach ($content->blocks as $block) {
            $this->assertNotEmpty($block->getId());
            $this->assertNotNull($block->getCreatedAt());
            $this->assertEquals('markdown', $block->getType());
            $this->assertIsInt($block->getWordCount());
            $this->assertIsBool($block->isEmpty());
        }

        // Test that we can work with blocks through the interface
        $totalWords = array_reduce(
            $content->blocks,
            fn (int $total, $block) => $total + $block->getWordCount(),
            0
        );

        $this->assertGreaterThan(0, $totalWords);
    }
}
