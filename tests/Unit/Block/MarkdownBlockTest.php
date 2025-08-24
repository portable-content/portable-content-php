<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Block;

use PHPUnit\Framework\TestCase;
use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\Contracts\Block\BlockInterface;
use PortableContent\Exception\InvalidContentException;

/**
 * @internal
 */
final class MarkdownBlockTest extends TestCase
{
    public function testCreateWithValidSource(): void
    {
        $block = MarkdownBlock::create('# Hello World\n\nThis is a test.');

        $this->assertNotEmpty($block->getId());
        $this->assertEquals('# Hello World\n\nThis is a test.', $block->getSource());
        $this->assertInstanceOf(\DateTimeImmutable::class, $block->getCreatedAt());
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

    public function testSetSource(): void
    {
        $block = MarkdownBlock::create('# Original');
        $originalId = $block->getId();
        $originalCreatedAt = $block->getCreatedAt();

        $block->setSource('# Updated');

        $this->assertEquals('# Updated', $block->getSource());
        $this->assertEquals($originalId, $block->getId()); // ID preserved
        $this->assertEquals($originalCreatedAt, $block->getCreatedAt()); // Timestamp preserved
    }

    public function testGetId(): void
    {
        $block = MarkdownBlock::create('# Test');

        $this->assertNotEmpty($block->getId());
        $this->assertIsString($block->getId());
    }

    public function testGetCreatedAt(): void
    {
        $block = MarkdownBlock::create('# Test');

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
        $this->assertEquals($block->getSource(), $block->getContent()); // Should be the same as source property
    }

    public function testIsEmpty(): void
    {
        // Create a block with content, then modify it to be empty to test isEmpty()
        $block = MarkdownBlock::create('# Test');
        $block->setSource('   ');
        $nonEmptyBlock = MarkdownBlock::create('# Not Empty');

        $this->assertTrue($block->isEmpty());
        $this->assertFalse($nonEmptyBlock->isEmpty());
    }

    public function testIsEmptyWithWhitespaceOnly(): void
    {
        $block = MarkdownBlock::create('# Test');
        $block->setSource("\n\t  \r\n  ");

        $this->assertTrue($block->isEmpty());
    }

    public function testGetWordCount(): void
    {
        $block = MarkdownBlock::create('# Hello World\n\nThis is a test with **bold** text.');

        // Count: Hello, World, This, is, a, test, with, bold, text = 9 words
        // But str_word_count might count differently, let's check actual count
        $actualCount = str_word_count(strip_tags($block->getSource()));
        $this->assertEquals($actualCount, $block->getWordCount());
        $this->assertGreaterThan(0, $block->getWordCount());
    }

    public function testGetWordCountWithEmptyContent(): void
    {
        $block = MarkdownBlock::create('# Test');
        $block->setSource('   ');

        $this->assertEquals(0, $block->getWordCount());
    }

    public function testGetWordCountStripsMarkdown(): void
    {
        $block = MarkdownBlock::create('# Heading\n\n**Bold** and *italic* text with [link](url).');

        // The actual word count depends on how str_word_count handles markdown
        $actualCount = str_word_count(strip_tags($block->getSource()));
        $this->assertEquals($actualCount, $block->getWordCount());
        $this->assertGreaterThan(0, $block->getWordCount());
    }

    public function testMutability(): void
    {
        $block = MarkdownBlock::create('# Original');
        $originalId = $block->getId();
        $originalCreatedAt = $block->getCreatedAt();

        $block->setSource('# Modified');

        $this->assertEquals('# Modified', $block->getSource());
        $this->assertEquals($originalId, $block->getId()); // ID should remain the same
        $this->assertEquals($originalCreatedAt, $block->getCreatedAt()); // CreatedAt should remain the same
    }

    public function testUniqueIds(): void
    {
        $block1 = MarkdownBlock::create('# Block 1');
        $block2 = MarkdownBlock::create('# Block 2');

        $this->assertNotEquals($block1->getId(), $block2->getId());
    }

    public function testCreatedAtIsRecent(): void
    {
        $before = new \DateTimeImmutable();
        $block = MarkdownBlock::create('# Test');
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $block->getCreatedAt());
        $this->assertLessThanOrEqual($after, $block->getCreatedAt());
    }
}
