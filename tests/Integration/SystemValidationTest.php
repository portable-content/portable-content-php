<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;
use PortableContent\Contracts\ContentRepositoryInterface;
use PortableContent\Tests\Support\Repository\RepositoryFactory;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Validation\BlockSanitizerManager;
use PortableContent\Validation\BlockValidatorManager;
use PortableContent\Validation\ContentSanitizer;
use PortableContent\Validation\ContentValidationService;
use PortableContent\Block\Markdown\MarkdownBlockSanitizer;
use PortableContent\Block\Markdown\MarkdownBlockValidator;
use Symfony\Component\Validator\Validation;

/**
 * System validation tests that verify the complete processing pipeline
 * with detailed monitoring and statistics.
 */
final class SystemValidationTest extends TestCase
{
    private ContentRepositoryInterface $repository;
    private ContentValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = RepositoryFactory::createInMemoryRepository();

        $blockSanitizerManager = new BlockSanitizerManager([
            new MarkdownBlockSanitizer()
        ]);
        $contentSanitizer = new ContentSanitizer($blockSanitizerManager);

        $blockValidatorManager = new BlockValidatorManager([
            new MarkdownBlockValidator()
        ]);
        $symfonyValidator = Validation::createValidator();
        $contentValidator = new SymfonyValidatorAdapter($symfonyValidator, $blockValidatorManager);

        $this->validationService = new ContentValidationService($contentSanitizer, $contentValidator);
    }

    public function testDetailedProcessingPipeline(): void
    {
        $rawInputData = [
            'type' => '  ARTICLE  ',
            'title' => '  Complete System Test  ',
            'summary' => "System validation\r\nwith\n\n\n\ndetailed monitoring",
            'blocks' => [
                [
                    'kind' => '  MARKDOWN  ',
                    'source' => "  # System Test  \n\n\n\n  This tests the **complete** pipeline.  \n\n  ## Features  \n\n  - Sanitization  \n  - Validation  \n  - Persistence  "
                ]
            ]
        ];

        // Use the detailed processing method to get full pipeline information
        $processingResult = $this->validationService->processContentWithDetails($rawInputData);

        // Verify structure of detailed result
        $this->assertArrayHasKey('sanitized_data', $processingResult);
        $this->assertArrayHasKey('sanitization_stats', $processingResult);
        $this->assertArrayHasKey('validation_result', $processingResult);
        $this->assertArrayHasKey('final_result', $processingResult);

        // Verify sanitization worked
        $sanitizedData = $processingResult['sanitized_data'];
        $this->assertIsArray($sanitizedData);
        $this->assertEquals('ARTICLE', $sanitizedData['type']); // Sanitizer preserves case
        $this->assertEquals('Complete System Test', $sanitizedData['title']);
        $this->assertEquals("System validation\nwith\n\ndetailed monitoring", $sanitizedData['summary']);

        // Verify sanitization statistics
        $stats = $processingResult['sanitization_stats'];
        $this->assertArrayHasKey('fields_processed', $stats);
        $this->assertArrayHasKey('fields_modified', $stats);
        $this->assertArrayHasKey('blocks_processed', $stats);
        $this->assertGreaterThan(0, $stats['fields_processed']);
        $this->assertGreaterThan(0, $stats['fields_modified']);
        $this->assertEquals(1, $stats['blocks_processed']);

        // Verify validation passed
        $validationResult = $processingResult['validation_result'];
        $this->assertTrue($validationResult->isValid());

        // Verify final result
        $finalResult = $processingResult['final_result'];
        $this->assertTrue($finalResult->isValid());
        $this->assertEquals($sanitizedData, $finalResult->getData());

        // Create domain objects and test persistence
        $block = MarkdownBlock::create($sanitizedData['blocks'][0]['source']);
        $content = ContentItem::create(
            $sanitizedData['type'],
            $sanitizedData['title'],
            $sanitizedData['summary'],
            [$block]
        );

        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);

        $this->assertNotNull($retrieved);
        $this->assertEquals($content->id, $retrieved->id);
        $this->assertEquals('ARTICLE', $retrieved->type); // Preserves case from sanitization
        $this->assertEquals('Complete System Test', $retrieved->title);
    }

    public function testSanitizationOnlyWorkflow(): void
    {
        $rawData = [
            'type' => '  note  ',
            'title' => '  Sanitization Test  ',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '  # Test Content  '
                ]
            ]
        ];

        // Test sanitization-only method
        $sanitizedData = $this->validationService->sanitizeContent($rawData);

        $this->assertEquals('note', $sanitizedData['type']);
        $this->assertEquals('Sanitization Test', $sanitizedData['title']);
        $this->assertEquals('# Test Content', $sanitizedData['blocks'][0]['source']);

        // Test sanitization statistics
        $stats = $this->validationService->getSanitizationStats($rawData, $sanitizedData);
        $this->assertArrayHasKey('fields_processed', $stats);
        $this->assertArrayHasKey('fields_modified', $stats);
        $this->assertGreaterThan(0, $stats['fields_modified']);
    }

    public function testValidationOnlyWorkflow(): void
    {
        // Pre-sanitized data
        $cleanData = [
            'type' => 'note',
            'title' => 'Clean Data Test',
            'summary' => 'This data is already clean',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Clean Content'
                ]
            ]
        ];

        // Test validation-only method
        $validationResult = $this->validationService->validateSanitizedContent($cleanData);

        $this->assertTrue($validationResult->isValid());
        $this->assertEmpty($validationResult->getErrors());
    }

    public function testCompleteErrorHandlingPipeline(): void
    {
        // Test sanitization error
        $sanitizationErrorData = [
            'type' => 'note',
            'title' => 'Error Test',
            'blocks' => [
                [
                    'kind' => 'unsupported_type',
                    'source' => 'Content'
                ]
            ]
        ];

        $result = $this->validationService->processContentWithDetails($sanitizationErrorData);
        
        $this->assertFalse($result['final_result']->isValid());
        $this->assertArrayHasKey('sanitization', $result['final_result']->getErrors());
        $this->assertEquals([], $result['sanitized_data']);
        $this->assertEquals([], $result['sanitization_stats']);

        // Test validation error (after successful sanitization)
        $validationErrorData = [
            'type' => '', // Empty type should fail validation
            'title' => 'Valid Title',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Valid Content'
                ]
            ]
        ];

        $validationResult = $this->validationService->validateContentCreation($validationErrorData);
        $this->assertFalse($validationResult->isValid());
        $errors = $validationResult->getErrors();
        $this->assertNotEmpty($errors);
    }

    public function testRepositoryIntegrationWithComplexData(): void
    {
        // Create complex content with multiple blocks
        $complexData = [
            'type' => 'documentation',
            'title' => 'API Documentation',
            'summary' => 'Complete API documentation with examples',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# API Overview\n\nThis document describes the API.'
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Authentication\n\nUse Bearer tokens for authentication.'
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Endpoints\n\n### GET /api/content\n\nRetrieve content items.'
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Examples\n\n```json\n{\n  "id": "123",\n  "type": "note"\n}\n```'
                ]
            ]
        ];

        // Process through complete pipeline
        $validationResult = $this->validationService->validateContentCreation($complexData);
        $this->assertTrue($validationResult->isValid());
        
        $sanitizedData = $validationResult->getData();
        $this->assertIsArray($sanitizedData);

        // Create domain objects
        $blocks = [];
        foreach ($sanitizedData['blocks'] as $blockData) {
            $blocks[] = MarkdownBlock::create($blockData['source']);
        }

        $content = ContentItem::create(
            $sanitizedData['type'],
            $sanitizedData['title'],
            $sanitizedData['summary'],
            $blocks
        );

        // Test repository operations
        $this->repository->save($content);
        
        $retrieved = $this->repository->findById($content->id);
        $this->assertNotNull($retrieved);
        $this->assertCount(4, $retrieved->blocks);
        
        // Verify each block was persisted correctly
        $this->assertStringContainsString('API Overview', $retrieved->blocks[0]->source);
        $this->assertStringContainsString('Authentication', $retrieved->blocks[1]->source);
        $this->assertStringContainsString('Endpoints', $retrieved->blocks[2]->source);
        $this->assertStringContainsString('Examples', $retrieved->blocks[3]->source);

        // Test update with fewer blocks
        $updatedBlocks = [
            MarkdownBlock::create('# Updated Overview\n\nThis is updated content.'),
            MarkdownBlock::create('## Updated Section\n\nNew information here.')
        ];

        $updatedContent = $content->withBlocks($updatedBlocks);
        $this->repository->save($updatedContent);

        $retrievedUpdated = $this->repository->findById($content->id);
        $this->assertNotNull($retrievedUpdated);
        $this->assertCount(2, $retrievedUpdated->blocks);
        $this->assertStringContainsString('Updated Overview', $retrievedUpdated->blocks[0]->source);
        $this->assertStringContainsString('Updated Section', $retrievedUpdated->blocks[1]->source);

        // Test findAll with multiple items
        $allContent = $this->repository->findAll();
        $this->assertCount(1, $allContent);
        $this->assertEquals($content->id, $allContent[0]->id);

        // Test deletion
        $this->repository->delete($content->id);
        $deletedContent = $this->repository->findById($content->id);
        $this->assertNull($deletedContent);
        
        $emptyList = $this->repository->findAll();
        $this->assertCount(0, $emptyList);
    }

    public function testSystemRobustnessWithVariousInputs(): void
    {
        $testCases = [
            // Minimal valid input
            [
                'type' => 'note',
                'blocks' => [['kind' => 'markdown', 'source' => '# Minimal']]
            ],
            // Maximum reasonable input
            [
                'type' => 'article',
                'title' => str_repeat('Long Title ', 10),
                'summary' => str_repeat('Long summary sentence. ', 20),
                'blocks' => [
                    ['kind' => 'markdown', 'source' => str_repeat('# Section\n\nContent. ', 50)]
                ]
            ],
            // Unicode and special characters
            [
                'type' => 'note',
                'title' => '测试 🚀 Тест',
                'summary' => 'Unicode: 中文 العربية русский 🎉',
                'blocks' => [
                    ['kind' => 'markdown', 'source' => '# 标题\n\n内容 with émojis 🎊']
                ]
            ]
        ];

        foreach ($testCases as $index => $testData) {
            $validationResult = $this->validationService->validateContentCreation($testData);
            $this->assertTrue($validationResult->isValid(), "Test case {$index} should be valid");
            
            $sanitizedData = $validationResult->getData();
            $this->assertIsArray($sanitizedData);

            $blocks = [];
            foreach ($sanitizedData['blocks'] as $blockData) {
                $blocks[] = MarkdownBlock::create($blockData['source']);
            }

            $content = ContentItem::create(
                $sanitizedData['type'],
                $sanitizedData['title'] ?? null,
                $sanitizedData['summary'] ?? null,
                $blocks
            );

            $this->repository->save($content);
            $retrieved = $this->repository->findById($content->id);
            
            $this->assertNotNull($retrieved, "Test case {$index} should persist correctly");
            $this->assertEquals($content->type, $retrieved->type);
            $this->assertCount(count($blocks), $retrieved->blocks);
        }
    }
}
