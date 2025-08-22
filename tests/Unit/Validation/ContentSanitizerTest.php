<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\Tests\TestCase;
use PortableContent\Validation\ContentSanitizer;
use PortableContent\Validation\BlockSanitizerManager;
use PortableContent\Block\Markdown\MarkdownBlockSanitizer;

/**
 * @internal
 */
final class ContentSanitizerTest extends TestCase
{
    private ContentSanitizer $sanitizer;
    private ContentSanitizer $sanitizerWithRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        // Create basic block registry for required parameter
        $basicRegistry = new BlockSanitizerManager([
            new MarkdownBlockSanitizer()
        ]);
        $this->sanitizer = new ContentSanitizer($basicRegistry);

        // Create sanitizer with block registry (same as basic for now)
        $blockRegistry = new BlockSanitizerManager([
            new MarkdownBlockSanitizer()
        ]);
        $this->sanitizerWithRegistry = new ContentSanitizer($blockRegistry);
    }

    public function testSanitizeValidData(): void
    {
        $data = [
            'type' => 'note',
            'title' => 'Test Note',
            'summary' => 'A test summary',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertEquals('note', $result['type']);
        $this->assertEquals('Test Note', $result['title']);
        $this->assertEquals('A test summary', $result['summary']);
        $this->assertIsArray($result['blocks']);
        $this->assertCount(1, $result['blocks']);
        $this->assertIsArray($result['blocks'][0]);
        $this->assertEquals('markdown', $result['blocks'][0]['kind']);
        $this->assertEquals('# Hello World', $result['blocks'][0]['source']);
    }

    public function testSanitizeTypeField(): void
    {
        $testCases = [
            ['input' => 'note', 'expected' => 'note'],
            ['input' => '  note  ', 'expected' => 'note'],
            ['input' => 'note-with-dashes', 'expected' => 'notewithdashes'],
            ['input' => 'note_with_underscores', 'expected' => 'note_with_underscores'],
            ['input' => 'note123', 'expected' => 'note123'],
            ['input' => 'note!@#$%', 'expected' => 'note'],
            ['input' => '', 'expected' => ''],
            ['input' => 123, 'expected' => '123'],
            ['input' => null, 'expected' => ''],
            ['input' => [], 'expected' => ''],
        ];

        foreach ($testCases as $case) {
            $data = ['type' => $case['input']];
            $result = $this->sanitizer->sanitize($data);
            if ($case['input'] === null) {
                // Null values are not included in the result
                $this->assertArrayNotHasKey('type', $result, "Failed for input: " . json_encode($case['input']));
            } else {
                $this->assertArrayHasKey('type', $result, "Failed for input: " . json_encode($case['input']));
                $this->assertEquals($case['expected'], $result['type'], "Failed for input: " . json_encode($case['input']));
            }
        }
    }

    public function testSanitizeTitleField(): void
    {
        $testCases = [
            ['input' => 'Test Title', 'expected' => 'Test Title'],
            ['input' => '  Test Title  ', 'expected' => 'Test Title'],
            ['input' => "Test\x00Title", 'expected' => 'TestTitle'],
            ['input' => "Test\tTitle", 'expected' => 'Test Title'], // Tab becomes space
            ['input' => "Test   Multiple   Spaces", 'expected' => 'Test Multiple Spaces'],
            ['input' => '', 'expected' => null],
            ['input' => '   ', 'expected' => null],
            ['input' => null, 'expected' => null],
            ['input' => 123, 'expected' => '123'],
            ['input' => [], 'expected' => null],
        ];

        foreach ($testCases as $case) {
            $data = ['title' => $case['input']];
            $result = $this->sanitizer->sanitize($data);
            if ($case['expected'] === null) {
                $this->assertArrayNotHasKey('title', $result);
            } else {
                $this->assertArrayHasKey('title', $result);
                $this->assertEquals($case['expected'], $result['title'], "Failed for input: " . json_encode($case['input']));
            }
        }
    }

    public function testSanitizeSummaryField(): void
    {
        $testCases = [
            ['input' => 'Test summary', 'expected' => 'Test summary'],
            ['input' => "Line 1\nLine 2", 'expected' => "Line 1\nLine 2"],
            ['input' => "Line 1\r\nLine 2", 'expected' => "Line 1\nLine 2"], // Normalize line endings
            ['input' => "Line 1\n\n\n\nLine 2", 'expected' => "Line 1\n\nLine 2"], // Limit consecutive newlines
            ['input' => "Test\x00Summary", 'expected' => 'TestSummary'], // Remove null bytes
            ['input' => '', 'expected' => null],
            ['input' => null, 'expected' => null],
            ['input' => [], 'expected' => null],
        ];

        foreach ($testCases as $case) {
            $data = ['summary' => $case['input']];
            $result = $this->sanitizer->sanitize($data);
            if ($case['expected'] === null) {
                $this->assertArrayNotHasKey('summary', $result);
            } else {
                $this->assertArrayHasKey('summary', $result);
                $this->assertEquals($case['expected'], $result['summary'], "Failed for input: " . json_encode($case['input']));
            }
        }
    }

    public function testSanitizeValidBlocksArray(): void
    {
        $data = [
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Test'
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Test 2'
                ],
            ]
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertIsArray($result['blocks']);
        $this->assertCount(2, $result['blocks']);
        $this->assertIsArray($result['blocks'][0]);
        $this->assertIsArray($result['blocks'][1]);
        $this->assertEquals('markdown', $result['blocks'][0]['kind']);
        $this->assertEquals('markdown', $result['blocks'][1]['kind']);
    }

    public function testSanitizeBlocksArrayWithInvalidBlockThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid block data at index 2: expected array, got string');

        $data = [
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Test'
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Test 2'
                ],
                'invalid_block', // This should cause an exception
            ]
        ];

        $this->sanitizer->sanitize($data);
    }

    public function testSanitizeBlockKindWithValidMarkdown(): void
    {
        $data = ['blocks' => [['kind' => 'markdown', 'source' => 'Test content']]];
        $result = $this->sanitizer->sanitize($data);

        $this->assertNotEmpty($result['blocks']);
        $this->assertIsArray($result['blocks']);
        $this->assertIsArray($result['blocks'][0]);
        $this->assertEquals('markdown', $result['blocks'][0]['kind']);
    }

    public function testSanitizeBlockKindWithInvalidTypes(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        // Test with unknown block type
        $data = ['blocks' => [['kind' => 'unknown', 'source' => 'Test content']]];
        $this->sanitizer->sanitize($data);
    }

    public function testSanitizeBlockSourceWithValidStrings(): void
    {
        $testCases = [
            ['input' => '# Hello World', 'expected' => '# Hello World'],
            ['input' => "Line 1\r\nLine 2", 'expected' => "Line 1\nLine 2"], // Normalize line endings
            ['input' => "Test\x00Content", 'expected' => 'TestContent'], // Remove null bytes
            ['input' => "Test\x01Content", 'expected' => 'TestContent'], // Remove control chars
            ['input' => '', 'expected' => ''],
        ];

        foreach ($testCases as $case) {
            $data = ['blocks' => [['kind' => 'markdown', 'source' => $case['input']]]];
            $result = $this->sanitizer->sanitize($data);
            $this->assertNotEmpty($result['blocks'], "Failed for input: " . json_encode($case['input']));
            $this->assertIsArray($result['blocks']);
            $this->assertIsArray($result['blocks'][0], "Failed for input: " . json_encode($case['input']));
            $this->assertArrayHasKey('source', $result['blocks'][0], "Failed for input: " . json_encode($case['input']));
            $this->assertEquals($case['expected'], $result['blocks'][0]['source'], "Failed for input: " . json_encode($case['input']));
        }
    }

    public function testSanitizeBlockSourceWithInvalidTypes(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block "source" must be a string');

        // Test with non-string source
        $data = ['blocks' => [['kind' => 'markdown', 'source' => 123]]];
        $this->sanitizer->sanitize($data);
    }

    public function testSanitizeWithBlockRegistry(): void
    {
        $data = [
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => "  # Hello World  \n\n\n\n  This is a test.  "
                ]
            ]
        ];

        $result = $this->sanitizerWithRegistry->sanitize($data);

        // The markdown block sanitizer should apply more specific sanitization
        $this->assertIsArray($result['blocks']);
        $this->assertCount(1, $result['blocks']);
        $this->assertIsArray($result['blocks'][0]);
        $this->assertEquals('markdown', $result['blocks'][0]['kind']);
        // The exact result depends on MarkdownBlockSanitizer implementation
        $this->assertIsString($result['blocks'][0]['source']);
    }

    public function testSanitizeEmptyData(): void
    {
        $result = $this->sanitizer->sanitize([]);
        $this->assertEmpty($result);
    }

    public function testSanitizePartialData(): void
    {
        $data = [
            'title' => 'Only Title'
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertEquals('Only Title', $result['title']);
        $this->assertArrayNotHasKey('type', $result);
        $this->assertArrayNotHasKey('summary', $result);
        $this->assertArrayNotHasKey('blocks', $result);
    }

    public function testGetSanitizationStats(): void
    {
        $originalData = [
            'type' => '  note  ',
            'title' => 'Test   Title',
            'summary' => "Test\n\n\n\nSummary",
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Original Content'
                ]
            ]
        ];

        $sanitizedData = $this->sanitizer->sanitize($originalData);
        $stats = $this->sanitizer->getSanitizationStats($originalData, $sanitizedData);

        $this->assertEquals(3, $stats['fields_processed']); // type, title, summary
        $this->assertGreaterThan(0, $stats['fields_modified']); // At least some fields were modified
        $this->assertEquals(1, $stats['blocks_processed']);
        $this->assertGreaterThan(0, $stats['total_content_length_before']);
        $this->assertGreaterThan(0, $stats['total_content_length_after']);
    }

    public function testSanitizeInvalidBlocksArrayThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid blocks data: expected array, got string');

        $data = ['blocks' => 'not_an_array'];
        $this->sanitizer->sanitize($data);
    }

    public function testSanitizeValidEmptyBlocksArray(): void
    {
        // Empty array should result in empty blocks array
        $data = ['blocks' => []];
        $result = $this->sanitizer->sanitize($data);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertEquals([], $result['blocks']);
    }

    public function testSanitizeNullBlocksIsIgnored(): void
    {
        // Null blocks should not be included in result (not an error)
        $data = ['blocks' => null];
        $result = $this->sanitizer->sanitize($data);
        $this->assertArrayNotHasKey('blocks', $result);
    }

    public function testSanitizePreservesValidUnicodeContent(): void
    {
        $data = [
            'title' => 'Test with émojis 🚀 and ñiño',
            'summary' => 'Content with 中文 and العربية',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Unicode Test 🌟\n\nContent with émojis and 中文字符'
                ]
            ]
        ];

        $result = $this->sanitizer->sanitize($data);

        $this->assertEquals('Test with émojis 🚀 and ñiño', $result['title']);
        $this->assertEquals('Content with 中文 and العربية', $result['summary']);
        $this->assertIsArray($result['blocks']);
        $this->assertIsArray($result['blocks'][0]);
        $this->assertEquals('# Unicode Test 🌟\n\nContent with émojis and 中文字符', $result['blocks'][0]['source']);
    }
}
