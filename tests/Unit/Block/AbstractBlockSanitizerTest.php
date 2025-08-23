<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Block;

use PortableContent\Block\AbstractBlockSanitizer;
use PortableContent\Tests\TestCase;

/**
 * @internal
 *
 */
final class AbstractBlockSanitizerTest extends TestCase
{
    private TestableBlockSanitizer $sanitizer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->sanitizer = new TestableBlockSanitizer();
    }

    public function testApplyBasicSanitization(): void
    {
        $testCases = [
            ['input' => "Hello\x00World", 'expected' => 'HelloWorld'],
            ['input' => "Line 1\r\nLine 2", 'expected' => "Line 1\nLine 2"],
            ['input' => "Test\x01Content", 'expected' => 'TestContent'],
            ['input' => 'Normal content', 'expected' => 'Normal content'],
        ];

        foreach ($testCases as $case) {
            $result = $this->sanitizer->testApplyBasicSanitization($case['input']);
            $this->assertEquals($case['expected'], $result, 'Failed for input: '.json_encode($case['input']));
        }
    }

    public function testApplyBasicKindSanitization(): void
    {
        $testCases = [
            ['input' => '  MARKDOWN  ', 'expected' => 'markdown'],
            ['input' => 'HTML-Block', 'expected' => 'htmlblock'],
            ['input' => 'code_block', 'expected' => 'codeblock'],
            ['input' => 'test123', 'expected' => 'test123'],
            ['input' => 'test!@#', 'expected' => 'test'],
            ['input' => '', 'expected' => ''],
        ];

        foreach ($testCases as $case) {
            $result = $this->sanitizer->testApplyBasicKindSanitization($case['input']);
            $this->assertEquals($case['expected'], $result, 'Failed for input: '.json_encode($case['input']));
        }
    }

    public function testValidateBlockDataSuccess(): void
    {
        $validData = [
            'kind' => 'markdown',
            'source' => '# Hello World',
        ];

        // Should not throw exception
        $this->sanitizer->testValidateBlockData($validData);
        $this->assertTrue(true); // If we get here, validation passed
    }

    public function testValidateBlockDataMissingKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block data must contain a "kind" field');

        $invalidData = [
            'source' => '# Hello World',
        ];

        $this->sanitizer->testValidateBlockData($invalidData);
    }

    public function testValidateBlockDataMissingSource(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block data must contain a "source" field');

        $invalidData = [
            'kind' => 'markdown',
        ];

        $this->sanitizer->testValidateBlockData($invalidData);
    }

    public function testValidateBlockDataInvalidKindType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block "kind" must be a string');

        $invalidData = [
            'kind' => 123,
            'source' => '# Hello World',
        ];

        $this->sanitizer->testValidateBlockData($invalidData);
    }

    public function testValidateBlockDataInvalidSourceType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block "source" must be a string');

        $invalidData = [
            'kind' => 'markdown',
            'source' => 123,
        ];

        $this->sanitizer->testValidateBlockData($invalidData);
    }
}

/**
 * Testable implementation of AbstractBlockSanitizer for testing protected methods.
 */
class TestableBlockSanitizer extends AbstractBlockSanitizer
{
    public function sanitize(array $blockData): array
    {
        // Not used in tests, just required by interface
        return $blockData;
    }

    public function supports(string $blockType): bool
    {
        return 'test' === $blockType;
    }

    public function getBlockType(): string
    {
        return 'test';
    }

    // Expose protected methods for testing
    public function testApplyBasicSanitization(string $source): string
    {
        return $this->applyBasicSanitization($source);
    }

    public function testApplyBasicKindSanitization(string $kind): string
    {
        return $this->applyBasicKindSanitization($kind);
    }

    /**
     * @param array<string, mixed> $blockData
     */
    public function testValidateBlockData(array $blockData): void
    {
        $this->validateBlockData($blockData);
    }
}
