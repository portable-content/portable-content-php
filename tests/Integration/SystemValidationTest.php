<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\Block\Markdown\MarkdownBlockSanitizer;
use PortableContent\Block\Markdown\MarkdownBlockValidator;
use PortableContent\ContentItem;
use PortableContent\Contracts\ContentRepositoryInterface;
use PortableContent\Tests\Support\Repository\RepositoryFactory;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Validation\BlockSanitizerManager;
use PortableContent\Validation\BlockValidatorManager;
use PortableContent\Validation\ContentSanitizer;
use PortableContent\Validation\ContentValidationService;
use Symfony\Component\Validator\Validation;

/**
 * System validation tests that verify the complete processing pipeline
 * with detailed monitoring and statistics.
 *
 * @internal
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
            new MarkdownBlockSanitizer(),
        ]);
        $contentSanitizer = new ContentSanitizer($blockSanitizerManager);

        $blockValidatorManager = new BlockValidatorManager([
            new MarkdownBlockValidator(),
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
                    'source' => "  # System Test  \n\n\n\n  This tests the **complete** pipeline.  \n\n  ## Features  \n\n  - Sanitization  \n  - Validation  \n  - Persistence  ",
                ],
            ],
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
        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertIsArray($sanitizedData['blocks'][0]);
        $this->assertIsString($sanitizedData['blocks'][0]['source']);
        $this->assertIsString($sanitizedData['type']);
        $this->assertIsString($sanitizedData['title']);
        $this->assertIsString($sanitizedData['summary']);

        $block = MarkdownBlock::create($sanitizedData['blocks'][0]['source']);
        $content = ContentItem::create(
            $sanitizedData['type'],
            $sanitizedData['title'],
            $sanitizedData['summary'],
            [$block]
        );

        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->getId());

        $this->assertNotNull($retrieved);
        $this->assertEquals($content->getId(), $retrieved->getId());
        $this->assertEquals('ARTICLE', $retrieved->getType()); // Preserves case from sanitization
        $this->assertEquals('Complete System Test', $retrieved->getTitle());
    }

    public function testSanitizationOnlyWorkflow(): void
    {
        $rawData = [
            'type' => '  note  ',
            'title' => '  Sanitization Test  ',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '  # Test Content  ',
                ],
            ],
        ];

        // Test sanitization-only method
        $sanitizedData = $this->validationService->sanitizeContent($rawData);

        $this->assertIsString($sanitizedData['type']);
        $this->assertEquals('note', $sanitizedData['type']);
        $this->assertIsString($sanitizedData['title']);
        $this->assertEquals('Sanitization Test', $sanitizedData['title']);
        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertIsArray($sanitizedData['blocks'][0]);
        $this->assertIsString($sanitizedData['blocks'][0]['source']);
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
                    'source' => '# Clean Content',
                ],
            ],
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
                    'source' => 'Content',
                ],
            ],
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
                    'source' => '# Valid Content',
                ],
            ],
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
                    'source' => '# API Overview\n\nThis document describes the API.',
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Authentication\n\nUse Bearer tokens for authentication.',
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Endpoints\n\n### GET /api/content\n\nRetrieve content items.',
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Examples\n\n```json\n{\n  "id": "123",\n  "type": "note"\n}\n```',
                ],
            ],
        ];

        // Process through complete pipeline
        $validationResult = $this->validationService->validateContentCreation($complexData);
        $this->assertTrue($validationResult->isValid());

        $sanitizedData = $validationResult->getData();
        $this->assertIsArray($sanitizedData);

        // Create domain objects
        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertIsString($sanitizedData['type']);
        $this->assertIsString($sanitizedData['title']);
        $this->assertIsString($sanitizedData['summary']);

        $blocks = [];
        foreach ($sanitizedData['blocks'] as $blockData) {
            $this->assertIsArray($blockData);
            $this->assertIsString($blockData['source']);
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

        $retrieved = $this->repository->findById($content->getId());
        $this->assertNotNull($retrieved);
        $this->assertCount(4, $retrieved->getBlocks());

        // Verify each block was persisted correctly
        /** @var MarkdownBlock $block0 */
        $block0 = $retrieved->getBlocks()[0];
        /** @var MarkdownBlock $block1 */
        $block1 = $retrieved->getBlocks()[1];
        /** @var MarkdownBlock $block2 */
        $block2 = $retrieved->getBlocks()[2];
        /** @var MarkdownBlock $block3 */
        $block3 = $retrieved->getBlocks()[3];

        $this->assertStringContainsString('API Overview', $block0->getContent());
        $this->assertStringContainsString('Authentication', $block1->getContent());
        $this->assertStringContainsString('Endpoints', $block2->getContent());
        $this->assertStringContainsString('Examples', $block3->getContent());

        // Test update with fewer blocks
        $updatedBlocks = [
            MarkdownBlock::create('# Updated Overview\n\nThis is updated content.'),
            MarkdownBlock::create('## Updated Section\n\nNew information here.'),
        ];

        $content->setBlocks($updatedBlocks);
        $this->repository->save($content);

        $retrievedUpdated = $this->repository->findById($content->getId());
        $this->assertNotNull($retrievedUpdated);
        $this->assertCount(2, $retrievedUpdated->getBlocks());
        /** @var MarkdownBlock $updatedBlock0 */
        $updatedBlock0 = $retrievedUpdated->getBlocks()[0];
        /** @var MarkdownBlock $updatedBlock1 */
        $updatedBlock1 = $retrievedUpdated->getBlocks()[1];
        $this->assertStringContainsString('Updated Overview', $updatedBlock0->getContent());
        $this->assertStringContainsString('Updated Section', $updatedBlock1->getContent());

        // Test findAll with multiple items
        $allContent = $this->repository->findAll();
        $this->assertCount(1, $allContent);
        $this->assertEquals($content->getId(), $allContent[0]->getId());

        // Test deletion
        $this->repository->delete($content->getId());
        $deletedContent = $this->repository->findById($content->getId());
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
                'blocks' => [['kind' => 'markdown', 'source' => '# Minimal']],
            ],
            // Maximum reasonable input
            [
                'type' => 'article',
                'title' => str_repeat('Long Title ', 10),
                'summary' => str_repeat('Long summary sentence. ', 20),
                'blocks' => [
                    ['kind' => 'markdown', 'source' => str_repeat('# Section\n\nContent. ', 50)],
                ],
            ],
            // Unicode and special characters
            [
                'type' => 'note',
                'title' => '测试 🚀 Тест',
                'summary' => 'Unicode: 中文 العربية русский 🎉',
                'blocks' => [
                    ['kind' => 'markdown', 'source' => '# 标题\n\n内容 with émojis 🎊'],
                ],
            ],
        ];

        foreach ($testCases as $index => $testData) {
            $validationResult = $this->validationService->validateContentCreation($testData);
            $this->assertTrue($validationResult->isValid(), "Test case {$index} should be valid");

            $sanitizedData = $validationResult->getData();
            $this->assertIsArray($sanitizedData);
            $this->assertIsArray($sanitizedData['blocks']);
            $this->assertIsString($sanitizedData['type']);

            $blocks = [];
            foreach ($sanitizedData['blocks'] as $blockData) {
                $this->assertIsArray($blockData);
                $this->assertIsString($blockData['source']);
                $blocks[] = MarkdownBlock::create($blockData['source']);
            }

            $title = isset($sanitizedData['title']) && is_string($sanitizedData['title']) ? $sanitizedData['title'] : null;
            $summary = isset($sanitizedData['summary']) && is_string($sanitizedData['summary']) ? $sanitizedData['summary'] : null;

            $content = ContentItem::create(
                $sanitizedData['type'],
                $title,
                $summary,
                $blocks
            );

            $this->repository->save($content);
            $retrieved = $this->repository->findById($content->getId());

            $this->assertNotNull($retrieved, "Test case {$index} should persist correctly");
            $this->assertEquals($content->getType(), $retrieved->getType());
            $this->assertCount(count($blocks), $retrieved->getBlocks());
        }
    }
}
