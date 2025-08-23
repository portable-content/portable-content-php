<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration\Validation;

use PortableContent\Block\Markdown\MarkdownBlockSanitizer;
use PortableContent\Block\Markdown\MarkdownBlockValidator;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Validation\BlockSanitizerManager;
use PortableContent\Validation\BlockValidatorManager;
use PortableContent\Validation\ContentSanitizer;
use PortableContent\Validation\ContentValidationService;
use Symfony\Component\Validator\Validation;

/**
 * Integration tests for ContentValidationService.
 *
 * These tests verify the complete sanitization → validation pipeline
 * using real implementations rather than mocks.
 *
 * @internal
 */
final class ContentValidationServiceIntegrationTest extends TestCase
{
    private ContentValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();

        // Create real block sanitizer manager
        $blockSanitizerManager = new BlockSanitizerManager([
            new MarkdownBlockSanitizer(),
        ]);

        // Create real content sanitizer
        $contentSanitizer = new ContentSanitizer($blockSanitizerManager);

        // Create real block validator manager
        $blockValidatorManager = new BlockValidatorManager([
            new MarkdownBlockValidator(),
        ]);

        // Create real content validator
        $symfonyValidator = Validation::createValidator();
        $contentValidator = new SymfonyValidatorAdapter($symfonyValidator, $blockValidatorManager);

        // Create the service with real implementations
        $this->service = new ContentValidationService($contentSanitizer, $contentValidator);
    }

    public function testCompleteValidationPipelineSuccess(): void
    {
        $inputData = [
            'type' => '  note  ', // Will be sanitized
            'title' => '  My Test Note  ', // Will be sanitized
            'summary' => "Test summary\r\nwith\n\n\n\nmultiple lines", // Will be sanitized
            'blocks' => [
                [
                    'kind' => '  MARKDOWN  ', // Will be sanitized to 'markdown'
                    'source' => "  # Hello World  \n\n\n\n  This is a test.  ", // Will be sanitized
                ],
            ],
        ];

        $result = $this->service->validateContentCreation($inputData);

        if (!$result->isValid()) {
            $this->fail('Validation failed with errors: '.json_encode($result->getErrors()));
        }

        $this->assertTrue($result->isValid(), 'Validation should pass for valid content');

        $sanitizedData = $result->getData();
        $this->assertIsArray($sanitizedData);

        // Verify content-level sanitization
        $this->assertEquals('note', $sanitizedData['type']);
        $this->assertEquals('My Test Note', $sanitizedData['title']);
        $this->assertEquals("Test summary\nwith\n\nmultiple lines", $sanitizedData['summary']);

        // Verify block-level sanitization
        $this->assertArrayHasKey('blocks', $sanitizedData);
        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertCount(1, $sanitizedData['blocks']);
        $this->assertIsArray($sanitizedData['blocks'][0]);
        $this->assertArrayHasKey('kind', $sanitizedData['blocks'][0]);
        $this->assertArrayHasKey('source', $sanitizedData['blocks'][0]);
        $this->assertEquals('markdown', $sanitizedData['blocks'][0]['kind']);
        // The markdown sanitizer preserves the structure but trims outer whitespace
        $this->assertEquals("# Hello World\n\n  This is a test.", $sanitizedData['blocks'][0]['source']);
    }

    public function testValidationFailureAfterSanitization(): void
    {
        $inputData = [
            'type' => 'note',
            'title' => str_repeat('A', 300), // Title too long (max 255 characters)
            'summary' => 'Test summary',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Test',
                ],
            ],
        ];

        $result = $this->service->validateContentCreation($inputData);

        $this->assertFalse($result->isValid(), 'Validation should fail for title too long');

        $errors = $result->getErrors();
        $this->assertArrayHasKey('title', $errors);
    }

    public function testSanitizationErrorPreventsValidation(): void
    {
        $inputData = [
            'type' => 'note',
            'title' => 'Test Note',
            'summary' => 'Test summary',
            'blocks' => [
                [
                    'kind' => 'unknown_block_type', // This will cause sanitization to fail
                    'source' => '# Test',
                ],
            ],
        ];

        $result = $this->service->validateContentCreation($inputData);

        $this->assertFalse($result->isValid(), 'Should fail due to sanitization error');

        $errors = $result->getErrors();
        $this->assertArrayHasKey('sanitization', $errors);
        $this->assertStringContainsString('No sanitizer registered for block type: unknown_block_type', $errors['sanitization'][0]);
    }

    public function testCompleteProcessingWithDetails(): void
    {
        $inputData = [
            'type' => '  note  ',
            'title' => '  Test Note  ',
            'summary' => "Summary\r\nwith\n\n\nlines",
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => "  # Hello  \n\n\n  Content  ",
                ],
            ],
        ];

        $result = $this->service->processContentWithDetails($inputData);

        // Verify structure
        $this->assertArrayHasKey('sanitized_data', $result);
        $this->assertArrayHasKey('sanitization_stats', $result);
        $this->assertArrayHasKey('validation_result', $result);
        $this->assertArrayHasKey('final_result', $result);

        // Verify sanitized data
        $sanitizedData = $result['sanitized_data'];
        $this->assertEquals('note', $sanitizedData['type']);
        $this->assertEquals('Test Note', $sanitizedData['title']);
        $this->assertEquals("Summary\nwith\n\nlines", $sanitizedData['summary']);

        // Verify sanitization stats
        $stats = $result['sanitization_stats'];
        $this->assertArrayHasKey('fields_processed', $stats);
        $this->assertArrayHasKey('fields_modified', $stats);
        $this->assertArrayHasKey('blocks_processed', $stats);
        $this->assertGreaterThan(0, $stats['fields_modified']);

        // Verify validation passed
        $this->assertTrue($result['validation_result']->isValid());
        $this->assertTrue($result['final_result']->isValid());
        $this->assertEquals($sanitizedData, $result['final_result']->getData());
    }

    public function testMultipleBlockTypes(): void
    {
        $inputData = [
            'type' => 'note',
            'title' => 'Multi-Block Note',
            'summary' => 'Note with multiple blocks',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# First Block',
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Second Block',
                ],
            ],
        ];

        $result = $this->service->validateContentCreation($inputData);

        $this->assertTrue($result->isValid());

        $sanitizedData = $result->getData();
        $this->assertIsArray($sanitizedData);
        $this->assertArrayHasKey('blocks', $sanitizedData);
        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertCount(2, $sanitizedData['blocks']);
        $this->assertIsArray($sanitizedData['blocks'][0]);
        $this->assertIsArray($sanitizedData['blocks'][1]);
        $this->assertArrayHasKey('kind', $sanitizedData['blocks'][0]);
        $this->assertArrayHasKey('kind', $sanitizedData['blocks'][1]);
        $this->assertEquals('markdown', $sanitizedData['blocks'][0]['kind']);
        $this->assertEquals('markdown', $sanitizedData['blocks'][1]['kind']);
    }

    public function testContentUpdateValidation(): void
    {
        $inputData = [
            'type' => 'note',
            'title' => 'Updated Note',
            'summary' => 'Updated summary',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Updated Content',
                ],
            ],
        ];

        $result = $this->service->validateContentUpdate($inputData);

        $this->assertTrue($result->isValid());

        $sanitizedData = $result->getData();
        $this->assertIsArray($sanitizedData);
        $this->assertArrayHasKey('title', $sanitizedData);
        $this->assertArrayHasKey('summary', $sanitizedData);
        $this->assertEquals('Updated Note', $sanitizedData['title']);
        $this->assertEquals('Updated summary', $sanitizedData['summary']);
    }

    public function testSanitizeContentOnly(): void
    {
        $inputData = [
            'type' => '  note  ',
            'title' => '  Test  ',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '  # Hello  ',
                ],
            ],
        ];

        $result = $this->service->sanitizeContent($inputData);

        $this->assertEquals('note', $result['type']);
        $this->assertArrayHasKey('title', $result);
        $this->assertArrayHasKey('blocks', $result);
        $this->assertIsArray($result['blocks']);
        $this->assertIsArray($result['blocks'][0]);
        $this->assertArrayHasKey('source', $result['blocks'][0]);
        $this->assertEquals('Test', $result['title']);
        $this->assertEquals('# Hello', $result['blocks'][0]['source']);
    }

    public function testValidateSanitizedContentOnly(): void
    {
        // Pre-sanitized data
        $sanitizedData = [
            'type' => 'note',
            'title' => 'Test Note',
            'summary' => 'Test summary',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Test',
                ],
            ],
        ];

        $result = $this->service->validateSanitizedContent($sanitizedData);

        $this->assertTrue($result->isValid());
    }

    public function testRealWorldComplexContent(): void
    {
        $inputData = [
            'type' => '  article  ',
            'title' => '  How to Use Portable Content  ',
            'summary' => "A comprehensive guide\r\nto using the\n\n\n\nportable content system.",
            'blocks' => [
                [
                    'kind' => '  MARKDOWN  ',
                    'source' => "  # Introduction  \n\n\n\nThis guide will help you understand portable content.  \n\n\n\n## Getting Started  \n\n  Follow these steps:  \n\n  1. Install the library  \n  2. Configure your blocks  \n  3. Start creating content  ",
                ],
                [
                    'kind' => 'markdown',
                    'source' => "  ## Advanced Usage  \n\n\n  For advanced users, consider these options:  \n\n  - Custom block types  \n  - Validation rules  \n  - Sanitization policies  ",
                ],
            ],
        ];

        $result = $this->service->validateContentCreation($inputData);

        $this->assertTrue($result->isValid(), 'Complex real-world content should validate successfully');

        $sanitizedData = $result->getData();

        // Verify content sanitization
        $this->assertIsArray($sanitizedData);
        $this->assertArrayHasKey('type', $sanitizedData);
        $this->assertArrayHasKey('title', $sanitizedData);
        $this->assertArrayHasKey('summary', $sanitizedData);
        $this->assertArrayHasKey('blocks', $sanitizedData);
        $this->assertEquals('article', $sanitizedData['type']);
        $this->assertEquals('How to Use Portable Content', $sanitizedData['title']);
        $this->assertEquals("A comprehensive guide\nto using the\n\nportable content system.", $sanitizedData['summary']);

        // Verify block sanitization
        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertCount(2, $sanitizedData['blocks']);

        // First block
        $this->assertIsArray($sanitizedData['blocks'][0]);
        $this->assertArrayHasKey('kind', $sanitizedData['blocks'][0]);
        $this->assertArrayHasKey('source', $sanitizedData['blocks'][0]);
        $this->assertEquals('markdown', $sanitizedData['blocks'][0]['kind']);
        $this->assertIsString($sanitizedData['blocks'][0]['source']);
        $this->assertStringContainsString('# Introduction', $sanitizedData['blocks'][0]['source']);
        $this->assertStringContainsString('## Getting Started', $sanitizedData['blocks'][0]['source']);

        // Second block
        $this->assertIsArray($sanitizedData['blocks'][1]);
        $this->assertArrayHasKey('kind', $sanitizedData['blocks'][1]);
        $this->assertArrayHasKey('source', $sanitizedData['blocks'][1]);
        $this->assertEquals('markdown', $sanitizedData['blocks'][1]['kind']);
        $this->assertIsString($sanitizedData['blocks'][1]['source']);
        $this->assertStringContainsString('## Advanced Usage', $sanitizedData['blocks'][1]['source']);
    }
}
