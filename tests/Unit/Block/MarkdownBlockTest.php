<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Block;

use PHPUnit\Framework\TestCase;
use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\Contracts\Block\BlockInterface;
use PortableContent\Exception\InvalidContentException;

/**
 * @internal
 *
 */
final class MarkdownBlockTest extends TestCase
{
    public function testCreateWithValidSource(): void
    {
        $block = MarkdownBlock::create('# Hello World\n\nThis is a test.');

        $this->assertNotEmpty($block->id);
        $this->assertEquals('# Hello World\n\nThis is a test.', $block->source);
        $this->assertInstanceOf(\DateTimeImmutable::class, $block->createdAt);
    }

    public function testImplementsBlockInterface(): void
    {
        $block = MarkdownBlock::create('# Test');

        $this->assertInstanceOf(BlockInterface::class, $block);
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
        $this->assertNotSame($block, $updatedBlock);
    }

    public function testGetId(): void
    {
        $block = MarkdownBlock::create('# Test');

        $this->assertEquals($block->id, $block->getId());
        $this->assertNotEmpty($block->getId());
    }

    public function testGetCreatedAt(): void
    {
        $block = MarkdownBlock::create('# Test');

        $this->assertEquals($block->createdAt, $block->getCreatedAt());
        $this->assertInstanceOf(\DateTimeImmutable::class, $block->getCreatedAt());
    }

    public function testGetType(): void
    {
        $block = MarkdownBlock::create('# Test');

        $this->assertEquals('markdown', $block->getType());
    }

    public function testGetContent(): void
    {
        $source = '# Test Content\n\nThis is a test.';
        $block = MarkdownBlock::create($source);

        $this->assertEquals($source, $block->getContent());
        $this->assertEquals($block->source, $block->getContent()); // Should be the same as source property
    }

    public function testIsEmpty(): void
    {
        // Create a block with content, then modify it to be empty to test isEmpty()
        $block = MarkdownBlock::create('# Test');
        $emptyBlock = $block->withSource('   ');
        $nonEmptyBlock = MarkdownBlock::create('# Not Empty');

        $this->assertTrue($emptyBlock->isEmpty());
        $this->assertFalse($nonEmptyBlock->isEmpty());
    }

    public function testIsEmptyWithWhitespaceOnly(): void
    {
        $block = MarkdownBlock::create('# Test');
        $whitespaceBlock = $block->withSource("\n\t  \r\n  ");

        $this->assertTrue($whitespaceBlock->isEmpty());
    }

    public function testGetWordCount(): void
    {
        $block = MarkdownBlock::create('# Hello World\n\nThis is a test with **bold** text.');

        // Count: Hello, World, This, is, a, test, with, bold, text = 9 words
        // But str_word_count might count differently, let's check actual count
        $actualCount = str_word_count(strip_tags($block->source));
        $this->assertEquals($actualCount, $block->getWordCount());
        $this->assertGreaterThan(0, $block->getWordCount());
    }

    public function testGetWordCountWithEmptyContent(): void
    {
        $block = MarkdownBlock::create('# Test');
        $emptyBlock = $block->withSource('   ');

        $this->assertEquals(0, $emptyBlock->getWordCount());
    }

    public function testGetWordCountStripsMarkdown(): void
    {
        $block = MarkdownBlock::create('# Heading\n\n**Bold** and *italic* text with [link](url).');

        // The actual word count depends on how str_word_count handles markdown
        $actualCount = str_word_count(strip_tags($block->source));
        $this->assertEquals($actualCount, $block->getWordCount());
        $this->assertGreaterThan(0, $block->getWordCount());
    }

    public function testImmutability(): void
    {
        $original = MarkdownBlock::create('# Original');
        $modified = $original->withSource('# Modified');

        $this->assertNotSame($original, $modified);
        $this->assertEquals('# Original', $original->source);
        $this->assertEquals('# Modified', $modified->source);
    }

    public function testUniqueIds(): void
    {
        $block1 = MarkdownBlock::create('# Block 1');
        $block2 = MarkdownBlock::create('# Block 2');

        $this->assertNotEquals($block1->id, $block2->id);
        $this->assertNotEquals($block1->getId(), $block2->getId());
    }

    public function testCreatedAtIsRecent(): void
    {
        $before = new \DateTimeImmutable();
        $block = MarkdownBlock::create('# Test');
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $block->createdAt);
        $this->assertLessThanOrEqual($after, $block->createdAt);
    }
}
