<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Contracts;

use PHPUnit\Framework\TestCase;
use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\Contracts\Block\BlockInterface;

/**
 * @internal
 *
 */
final class BlockInterfaceTest extends TestCase
{
    public function testMarkdownBlockImplementsInterface(): void
    {
        $block = MarkdownBlock::create('# Test');

        $this->assertInstanceOf(BlockInterface::class, $block);
    }

    public function testInterfaceMethodsAreCallable(): void
    {
        $block = MarkdownBlock::create('# Test Block\n\nSome content here.');

        // Test all interface methods are callable
        $this->assertIsString($block->getId());
        $this->assertInstanceOf(\DateTimeImmutable::class, $block->getCreatedAt());
        $this->assertIsBool($block->isEmpty());
        $this->assertIsInt($block->getWordCount());
        $this->assertIsString($block->getType());
        $this->assertIsString($block->getContent());
    }

    public function testInterfaceContractIsRespected(): void
    {
        $block = MarkdownBlock::create('# Test Block\n\nSome content here.');

        // Test that interface methods return expected types
        $this->assertNotEmpty($block->getId());
        $this->assertFalse($block->isEmpty());
        $this->assertGreaterThan(0, $block->getWordCount());
        $this->assertEquals('markdown', $block->getType());
        $this->assertNotEmpty($block->getContent());
        $this->assertStringContainsString('Test Block', $block->getContent());
    }
}
