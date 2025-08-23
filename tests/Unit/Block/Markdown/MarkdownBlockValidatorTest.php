<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Block\Markdown;

use PortableContent\Block\Markdown\MarkdownBlockValidator;
use PortableContent\Tests\TestCase;

/**
 * @internal
 *
 */
final class MarkdownBlockValidatorTest extends TestCase
{
    private MarkdownBlockValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new MarkdownBlockValidator();
    }

    public function testSupportsMarkdownBlockType(): void
    {
        $this->assertTrue($this->validator->supports('markdown'));
        $this->assertFalse($this->validator->supports('code'));
        $this->assertFalse($this->validator->supports('image'));
    }

    public function testGetBlockType(): void
    {
        $this->assertEquals('markdown', $this->validator->getBlockType());
    }

    public function testValidateWithValidMarkdownBlock(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => '# Hello World\n\nThis is a test.',
        ];

        $result = $this->validator->validate($blockData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateWithMissingKind(): void
    {
        $blockData = [
            'source' => '# Hello World',
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('kind', $result->getErrors());
        $this->assertContains('Block kind is required', $result->getFieldErrors('kind'));
    }

    public function testValidateWithWrongKind(): void
    {
        $blockData = [
            'kind' => 'code',
            'source' => '# Hello World',
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('kind', $result->getErrors());
        $this->assertContains('This validator only handles markdown blocks', $result->getFieldErrors('kind'));
    }

    public function testValidateWithMissingSource(): void
    {
        $blockData = [
            'kind' => 'markdown',
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('source', $result->getErrors());
        $this->assertContains('Block source is required', $result->getFieldErrors('source'));
    }

    public function testValidateWithNonStringSource(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => 123,
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('source', $result->getErrors());
        $this->assertContains('Block source must be a string', $result->getFieldErrors('source'));
    }

    public function testValidateWithEmptySource(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => '   ',
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('source', $result->getErrors());
        $this->assertContains('Block source cannot be empty after trimming', $result->getFieldErrors('source'));
    }

    public function testValidateWithTooLongSource(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => str_repeat('a', 100001), // Exceeds 100KB limit
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('source', $result->getErrors());
        $errors = $result->getFieldErrors('source');
        $this->assertCount(1, $errors);
        $this->assertStringContainsString('Block source cannot exceed 100000 characters', $errors[0]);
    }

    public function testValidateWithUnbalancedCodeBlocks(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "# Test\n\n```php\necho 'hello';\n// Missing closing backticks",
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('source', $result->getErrors());
        $this->assertContains('Unbalanced code blocks (``` markers)', $result->getFieldErrors('source'));
    }

    public function testValidateWithBalancedCodeBlocks(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "# Test\n\n```php\necho 'hello';\n```\n\nMore content.",
        ];

        $result = $this->validator->validate($blockData);

        $this->assertTrue($result->isValid());
    }

    public function testValidateWithInvalidLinks(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "# Test\n\n[Link](javascript:alert('xss'))",
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('source', $result->getErrors());
        $this->assertContains('Links must use valid URLs or relative paths', $result->getFieldErrors('source'));
    }

    public function testValidateWithValidLinks(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => "# Test\n\n[HTTP Link](https://example.com)\n[Relative](/path)\n[Anchor](#section)",
        ];

        $result = $this->validator->validate($blockData);

        $this->assertTrue($result->isValid());
    }

    public function testValidateWithMultipleErrors(): void
    {
        $blockData = [
            'kind' => 'code', // Wrong kind
            'source' => "```\ncode\n[bad link](bad:url)", // Unbalanced code blocks + bad link
        ];

        $result = $this->validator->validate($blockData);

        $this->assertFalse($result->isValid());
        $this->assertArrayHasKey('kind', $result->getErrors());
        $this->assertArrayHasKey('source', $result->getErrors());
        $this->assertGreaterThanOrEqual(2, $result->getErrorCount());
    }

    public function testValidateWithMinimumValidContent(): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => 'a', // Single character
        ];

        $result = $this->validator->validate($blockData);

        $this->assertTrue($result->isValid());
    }
}
