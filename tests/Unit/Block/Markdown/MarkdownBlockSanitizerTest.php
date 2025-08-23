<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Block\Markdown;

use PortableContent\Block\Markdown\MarkdownBlockSanitizer;
use PortableContent\Tests\TestCase;

/**
 * @internal
 */
final class MarkdownBlockSanitizerTest extends TestCase
{
    private MarkdownBlockSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new MarkdownBlockSanitizer();
    }

    public function testSupportsMarkdownBlockType(): void
    {
        $this->assertTrue($this->sanitizer->supports('markdown'));
        $this->assertFalse($this->sanitizer->supports('code'));
        $this->assertFalse($this->sanitizer->supports('image'));
    }

    public function testGetBlockType(): void
    {
        $this->assertEquals('markdown', $this->sanitizer->getBlockType());
    }

    public function testSanitizeTrimsKind(): void
    {
        $blockData = [
            'kind' => '  markdown  ',
            'source' => 'content',
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals('markdown', $result['kind']);
    }

    public function testSanitizeNormalizesLineEndings(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "Line 1\r\nLine 2\rLine 3\nLine 4",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals("Line 1\nLine 2\nLine 3\nLine 4", $result['source']);
    }

    public function testSanitizeRemovesTrailingWhitespace(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "Line 1   \nLine 2\t\nLine 3 ",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals("Line 1\nLine 2\nLine 3", $result['source']);
    }

    public function testSanitizeRemovesExcessiveBlankLines(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "Line 1\n\n\n\n\nLine 2",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals("Line 1\n\nLine 2", $result['source']);
    }

    public function testSanitizeNormalizesHeadingWhitespace(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "#    Title 1\n##\t\tTitle 2\n###   Title 3",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals("# Title 1\n## Title 2\n### Title 3", $result['source']);
    }

    public function testSanitizeNormalizesListItemSpacing(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "-    Item 1\n*\t\tItem 2\n+   Item 3\n1.\t\tNumbered 1\n2.    Numbered 2",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals("- Item 1\n* Item 2\n+ Item 3\n1. Numbered 1\n2. Numbered 2", $result['source']);
    }

    public function testSanitizeTrimsEntireContent(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "\n\n  # Title\n\nContent\n\n  ",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals("# Title\n\nContent", $result['source']);
    }

    public function testSanitizePreservesIndentedContent(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "    Code block\n        Indented more\n    Back to original",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        // The sanitizer trims leading whitespace from the entire content but preserves relative indentation
        $this->assertEquals("Code block\n        Indented more\n    Back to original", $result['source']);
    }

    public function testSanitizeHandlesEmptyContent(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => '',
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals('', $result['source']);
    }

    public function testSanitizeHandlesWhitespaceOnlyContent(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "   \n\t\n   ",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals('', $result['source']);
    }

    public function testSanitizeOnlyReturnsKindAndSource(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => '# Title',
            'metadata' => ['key' => 'value'], // This should not be preserved
            'custom' => 'field', // This should not be preserved
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $this->assertEquals('markdown', $result['kind']);
        $this->assertEquals('# Title', $result['source']);
        $this->assertArrayNotHasKey('metadata', $result); // Extra fields not preserved
        $this->assertArrayNotHasKey('custom', $result); // Extra fields not preserved
        $this->assertCount(2, $result); // Only kind and source
    }

    public function testSanitizeWithMissingKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block data must contain a "kind" field');

        $blockData = [
            'source' => '# Title',
        ];

        $this->sanitizer->sanitize($blockData);
    }

    public function testSanitizeWithMissingSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block data must contain a "source" field');

        $blockData = [
            'kind' => 'markdown',
        ];

        $this->sanitizer->sanitize($blockData);
    }

    public function testSanitizeWithNonStringSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block "source" must be a string');

        $blockData = [
            'kind' => 'markdown',
            'source' => 123,
        ];

        $this->sanitizer->sanitize($blockData);
    }

    public function testSanitizeComplexMarkdownDocument(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "\n\n#    Main Title   \n\n\n\n##\t\tSubtitle\n\n-    Item 1   \n*\t\tItem 2\n\n\n```php\necho 'code';\n```\n\n\n",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        $expected = "# Main Title\n\n## Subtitle\n\n- Item 1\n* Item 2\n\n```php\necho 'code';\n```";
        $this->assertEquals($expected, $result['source']);
    }

    public function testSanitizePreservesCodeBlockFormatting(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "```php\n    function test() {\n        return 'hello';\n    }\n```",
        ];

        $result = $this->sanitizer->sanitize($blockData);

        // The sanitizer preserves the internal structure but removes trailing whitespace
        $this->assertEquals("```php\n    function test() {\n        return 'hello';\n    }\n```", $result['source']);
    }
}
