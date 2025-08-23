<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation\ValueObjects;

use PortableContent\Tests\TestCase;
use PortableContent\Validation\ValueObjects\BlockData;

/**
 * @internal
 *
 */
final class BlockDataTest extends TestCase
{
    public function testConstructorSetsPropertiesCorrectly(): void
    {
        $block = new BlockData('markdown', '# Hello World');

        $this->assertEquals('markdown', $block->kind);
        $this->assertEquals('# Hello World', $block->source);
    }

    public function testFromArrayCreatesCorrectInstance(): void
    {
        $data = [
            'kind' => 'html',
            'source' => '<p>Hello World</p>',
        ];

        $block = BlockData::fromArray($data);

        $this->assertEquals('html', $block->kind);
        $this->assertEquals('<p>Hello World</p>', $block->source);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $block = new BlockData('markdown', '## Heading');

        $array = $block->toArray();

        $expected = [
            'kind' => 'markdown',
            'source' => '## Heading',
        ];

        $this->assertEquals($expected, $array);
    }

    public function testIsEmptyReturnsTrueForEmptySource(): void
    {
        $block = new BlockData('markdown', '');

        $this->assertTrue($block->isEmpty());
    }

    public function testIsEmptyReturnsTrueForWhitespaceOnlySource(): void
    {
        $block = new BlockData('markdown', "   \n\t  ");

        $this->assertTrue($block->isEmpty());
    }

    public function testIsEmptyReturnsFalseForNonEmptySource(): void
    {
        $block = new BlockData('markdown', 'Content');

        $this->assertFalse($block->isEmpty());
    }

    public function testGetContentLengthReturnsCorrectLength(): void
    {
        $block = new BlockData('markdown', 'Hello World');

        $this->assertEquals(11, $block->getContentLength());
    }

    public function testGetContentLengthForEmptySource(): void
    {
        $block = new BlockData('markdown', '');

        $this->assertEquals(0, $block->getContentLength());
    }

    public function testGetWordCountReturnsCorrectCount(): void
    {
        $block = new BlockData('markdown', 'Hello world this is a test');

        $this->assertEquals(6, $block->getWordCount());
    }

    public function testGetWordCountForEmptySource(): void
    {
        $block = new BlockData('markdown', '');

        $this->assertEquals(0, $block->getWordCount());
    }

    public function testGetWordCountForWhitespaceOnlySource(): void
    {
        $block = new BlockData('markdown', "   \n\t  ");

        $this->assertEquals(0, $block->getWordCount());
    }

    public function testGetWordCountWithPunctuation(): void
    {
        $block = new BlockData('markdown', 'Hello, world! How are you?');

        $this->assertEquals(5, $block->getWordCount());
    }

    public function testGetLineCountForSingleLine(): void
    {
        $block = new BlockData('markdown', 'Single line content');

        $this->assertEquals(1, $block->getLineCount());
    }

    public function testGetLineCountForMultipleLines(): void
    {
        $block = new BlockData('markdown', "Line 1\nLine 2\nLine 3");

        $this->assertEquals(3, $block->getLineCount());
    }

    public function testGetLineCountForEmptySource(): void
    {
        $block = new BlockData('markdown', '');

        $this->assertEquals(0, $block->getLineCount());
    }

    public function testGetLineCountWithTrailingNewline(): void
    {
        $block = new BlockData('markdown', "Line 1\nLine 2\n");

        $this->assertEquals(3, $block->getLineCount());
    }

    public function testIsMarkdownReturnsTrueForMarkdownKind(): void
    {
        $block = new BlockData('markdown', 'Content');

        $this->assertTrue($block->isMarkdown());
    }

    public function testIsMarkdownReturnsFalseForOtherKinds(): void
    {
        $htmlBlock = new BlockData('html', '<p>Content</p>');
        $codeBlock = new BlockData('code', 'console.log("hello");');

        $this->assertFalse($htmlBlock->isMarkdown());
        $this->assertFalse($codeBlock->isMarkdown());
    }

    public function testWithSourceCreatesNewInstance(): void
    {
        $original = new BlockData('markdown', 'Original content');

        $updated = $original->withSource('New content');

        $this->assertEquals('Original content', $original->source);
        $this->assertEquals('New content', $updated->source);
        $this->assertEquals($original->kind, $updated->kind);
    }

    public function testWithKindCreatesNewInstance(): void
    {
        $original = new BlockData('markdown', 'Content');

        $updated = $original->withKind('html');

        $this->assertEquals('markdown', $original->kind);
        $this->assertEquals('html', $updated->kind);
        $this->assertEquals($original->source, $updated->source);
    }

    public function testMarkdownFactoryMethodCreatesMarkdownBlock(): void
    {
        $block = BlockData::markdown('# Heading');

        $this->assertEquals('markdown', $block->kind);
        $this->assertEquals('# Heading', $block->source);
        $this->assertTrue($block->isMarkdown());
    }

    public function testContainsReturnsTrueWhenTextExists(): void
    {
        $block = new BlockData('markdown', 'Hello world, this is a test');

        $this->assertTrue($block->contains('world'));
        $this->assertTrue($block->contains('Hello'));
        $this->assertTrue($block->contains('test'));
    }

    public function testContainsReturnsFalseWhenTextDoesNotExist(): void
    {
        $block = new BlockData('markdown', 'Hello world');

        $this->assertFalse($block->contains('goodbye'));
        $this->assertFalse($block->contains('HELLO')); // Case sensitive
    }

    public function testContainsIsCaseSensitive(): void
    {
        $block = new BlockData('markdown', 'Hello World');

        $this->assertTrue($block->contains('Hello'));
        $this->assertFalse($block->contains('hello'));
        $this->assertTrue($block->contains('World'));
        $this->assertFalse($block->contains('world'));
    }

    public function testStartsWithReturnsTrueWhenTextAtBeginning(): void
    {
        $block = new BlockData('markdown', '# Heading content');

        $this->assertTrue($block->startsWith('#'));
        $this->assertTrue($block->startsWith('# Heading'));
    }

    public function testStartsWithReturnsFalseWhenTextNotAtBeginning(): void
    {
        $block = new BlockData('markdown', 'Content # Heading');

        $this->assertFalse($block->startsWith('#'));
        $this->assertFalse($block->startsWith('Heading'));
    }

    public function testEndsWithReturnsTrueWhenTextAtEnd(): void
    {
        $block = new BlockData('markdown', 'Content ends here.');

        $this->assertTrue($block->endsWith('.'));
        $this->assertTrue($block->endsWith('here.'));
    }

    public function testEndsWithReturnsFalseWhenTextNotAtEnd(): void
    {
        $block = new BlockData('markdown', 'Content here. More content');

        $this->assertFalse($block->endsWith('.'));
        $this->assertFalse($block->endsWith('here.'));
    }

    public function testGetPreviewReturnsFullContentWhenShorterThanLimit(): void
    {
        $content = 'Short content';
        $block = new BlockData('markdown', $content);

        $preview = $block->getPreview(100);

        $this->assertEquals($content, $preview);
    }

    public function testGetPreviewTruncatesLongContent(): void
    {
        $content = str_repeat('a', 200);
        $block = new BlockData('markdown', $content);

        $preview = $block->getPreview(50);

        $this->assertEquals(str_repeat('a', 50).'...', $preview);
        $this->assertEquals(53, strlen($preview)); // 50 + 3 for '...'
    }

    public function testGetPreviewWithCustomLength(): void
    {
        $content = 'This is a longer piece of content that should be truncated';
        $block = new BlockData('markdown', $content);

        $preview = $block->getPreview(10);

        $this->assertEquals('This is a ...', $preview);
    }

    public function testGetPreviewWithZeroLength(): void
    {
        $block = new BlockData('markdown', 'Content');

        $preview = $block->getPreview(0);

        $this->assertEquals('...', $preview);
    }

    public function testRoundTripArrayConversion(): void
    {
        $originalData = [
            'kind' => 'markdown',
            'source' => '# Test Content\n\nThis is a test.',
        ];

        $block = BlockData::fromArray($originalData);
        $convertedData = $block->toArray();

        $this->assertEquals($originalData, $convertedData);
    }

    public function testImmutabilityOfProperties(): void
    {
        $block = new BlockData('markdown', 'Original');

        // Properties should be readonly - this is enforced by PHP's readonly keyword
        // We can test that methods return new instances rather than modifying the original
        $withNewSource = $block->withSource('Modified');
        $withNewKind = $block->withKind('html');

        $this->assertEquals('Original', $block->source);
        $this->assertEquals('markdown', $block->kind);
        $this->assertEquals('Modified', $withNewSource->source);
        $this->assertEquals('html', $withNewKind->kind);
    }
}
