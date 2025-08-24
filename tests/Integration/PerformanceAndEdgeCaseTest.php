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
 * Performance and edge case tests for the complete system.
 *
 * @internal
 */
final class PerformanceAndEdgeCaseTest extends TestCase
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

    public function testLargeContentHandling(): void
    {
        // Test with large content that's still within reasonable limits
        $largeMarkdownContent = str_repeat("# Section\n\nThis is a large section with lots of content. ", 100);
        $largeMarkdownContent .= "\n\n" . str_repeat("- List item with substantial content\n", 50);

        $inputData = [
            'type' => 'article',
            'title' => 'Large Content Test',
            'summary' => str_repeat('This is a summary sentence. ', 20),
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => $largeMarkdownContent,
                ],
            ],
        ];

        $startTime = microtime(true);

        // Validate and sanitize
        $validationResult = $this->validationService->validateContentCreation($inputData);
        $this->assertTrue($validationResult->isValid());

        $sanitizedData = $validationResult->getData();
        $this->assertIsArray($sanitizedData);

        // Create domain objects
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

        // Save to database
        $this->repository->save($content);

        // Retrieve from database
        $retrieved = $this->repository->findById($content->id);

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        $this->assertNotNull($retrieved);
        $this->assertEquals($content->id, $retrieved->id);
        /** @var MarkdownBlock $firstBlock */
        $firstBlock = $retrieved->blocks[0];
        $this->assertStringContainsString('Section', $firstBlock->source);

        // Performance assertion - should complete within reasonable time (adjust as needed)
        $this->assertLessThan(1.0, $processingTime, 'Large content processing should complete within 1 second');
    }

    public function testMultipleContentItemsPerformance(): void
    {
        $startTime = microtime(true);

        $contentIds = [];

        // Create and save multiple content items
        for ($i = 1; $i <= 50; ++$i) {
            $inputData = [
                'type' => 'note',
                'title' => "Test Note {$i}",
                'summary' => "Summary for note {$i}",
                'blocks' => [
                    [
                        'kind' => 'markdown',
                        'source' => "# Note {$i}\n\nThis is the content for note number {$i}.",
                    ],
                ],
            ];

            $validationResult = $this->validationService->validateContentCreation($inputData);
            $this->assertTrue($validationResult->isValid(), "Validation failed for item {$i}");

            $sanitizedData = $validationResult->getData();
            $this->assertIsArray($sanitizedData, "Sanitized data should be array for item {$i}");
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
            $contentIds[] = $content->id;

            // Verify save worked by checking count periodically
            if (0 === $i % 10) {
                $currentCount = count($this->repository->findAll(100)); // Use higher limit
                $this->assertEquals($i, $currentCount, "Expected {$i} items after saving item {$i}, got {$currentCount}");
            }
        }

        // Retrieve all content (with higher limit than default 20)
        $allContent = $this->repository->findAll(100);
        $this->assertCount(50, $allContent, 'Expected 50 content items, got ' . count($allContent));

        // Retrieve individual items
        foreach ($contentIds as $id) {
            $retrieved = $this->repository->findById($id);
            $this->assertNotNull($retrieved);
        }

        $endTime = microtime(true);
        $processingTime = $endTime - $startTime;

        // Performance assertion - should handle 50 items efficiently
        $this->assertLessThan(5.0, $processingTime, 'Processing 50 content items should complete within 5 seconds');
    }

    public function testUnicodeAndSpecialCharacterHandling(): void
    {
        $inputData = [
            'type' => 'note',
            'title' => 'Unicode Test: 🚀 Émojis & Spëcial Chärs',
            'summary' => 'Testing unicode: 中文, العربية, русский, 日本語',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => "# Unicode Content 🌍\n\n- Chinese: 你好世界\n- Arabic: مرحبا بالعالم\n- Russian: Привет мир\n- Japanese: こんにちは世界\n- Emoji: 🎉🎊🚀💻📝",
                ],
            ],
        ];

        $validationResult = $this->validationService->validateContentCreation($inputData);
        $this->assertTrue($validationResult->isValid());

        $sanitizedData = $validationResult->getData();
        $this->assertIsArray($sanitizedData);
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
        $retrieved = $this->repository->findById($content->id);

        $this->assertNotNull($retrieved);
        $this->assertNotNull($retrieved->title);
        $this->assertStringContainsString('🚀', $retrieved->title);
        $this->assertNotNull($retrieved->summary);
        $this->assertStringContainsString('中文', $retrieved->summary);
        /** @var MarkdownBlock $firstBlock */
        $firstBlock = $retrieved->blocks[0];
        $this->assertStringContainsString('你好世界', $firstBlock->source);
        $this->assertStringContainsString('🎉', $firstBlock->source);
    }

    public function testEdgeCaseLineEndingsAndWhitespace(): void
    {
        // Test various line ending combinations and whitespace scenarios
        $inputData = [
            'type' => 'note',
            'title' => "  \t  Mixed Whitespace  \t  ",
            'summary' => "Line 1\r\nLine 2\rLine 3\nLine 4\n\n\n\nToo many newlines",
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => "  \t# Title with leading whitespace\t  \r\n\r\n\r\nContent with mixed line endings\r\nand trailing spaces  \t\r\n\r\n\r\n\r\nToo many blank lines",
                ],
            ],
        ];

        $validationResult = $this->validationService->validateContentCreation($inputData);
        $this->assertTrue($validationResult->isValid());

        $sanitizedData = $validationResult->getData();
        $this->assertIsArray($sanitizedData);

        // Verify sanitization cleaned up whitespace and line endings
        $this->assertIsString($sanitizedData['title']);
        $this->assertEquals('Mixed Whitespace', $sanitizedData['title']);
        $this->assertIsString($sanitizedData['summary']);
        $this->assertStringNotContainsString("\r", $sanitizedData['summary']);
        $this->assertStringNotContainsString("\n\n\n\n", $sanitizedData['summary']);

        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertIsArray($sanitizedData['blocks'][0]);
        $this->assertIsString($sanitizedData['blocks'][0]['source']);
        $sanitizedSource = $sanitizedData['blocks'][0]['source'];
        $this->assertStringNotContainsString("\r", $sanitizedSource);
        $this->assertStringNotContainsString("  \t", $sanitizedSource);

        $this->assertIsString($sanitizedData['type']);
        $block = MarkdownBlock::create($sanitizedSource);
        $content = ContentItem::create(
            $sanitizedData['type'],
            $sanitizedData['title'],
            $sanitizedData['summary'],
            [$block]
        );

        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);

        $this->assertNotNull($retrieved);
        $this->assertEquals('Mixed Whitespace', $retrieved->title);
    }

    public function testEmptyAndNullValueHandling(): void
    {
        // Test with minimal valid data (only required fields)
        $minimalData = [
            'type' => 'note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Minimal Content',
                ],
            ],
        ];

        $validationResult = $this->validationService->validateContentCreation($minimalData);
        $this->assertTrue($validationResult->isValid());

        $sanitizedData = $validationResult->getData();
        $this->assertIsArray($sanitizedData);
        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertIsArray($sanitizedData['blocks'][0]);
        $this->assertIsString($sanitizedData['blocks'][0]['source']);
        $this->assertIsString($sanitizedData['type']);

        $block = MarkdownBlock::create($sanitizedData['blocks'][0]['source']);
        $content = ContentItem::create(
            $sanitizedData['type'],
            null, // No title
            null, // No summary
            [$block]
        );

        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);

        $this->assertNotNull($retrieved);
        $this->assertEquals('note', $retrieved->type);
        $this->assertNull($retrieved->title);
        $this->assertNull($retrieved->summary);
        $this->assertCount(1, $retrieved->blocks);
    }

    public function testConcurrentOperationsSimulation(): void
    {
        // Simulate concurrent operations by rapidly creating, updating, and reading content
        $content1Data = [
            'type' => 'note',
            'title' => 'Concurrent Test 1',
            'blocks' => [['kind' => 'markdown', 'source' => '# Content 1']],
        ];

        $content2Data = [
            'type' => 'note',
            'title' => 'Concurrent Test 2',
            'blocks' => [['kind' => 'markdown', 'source' => '# Content 2']],
        ];

        // Create first content
        $result1 = $this->validationService->validateContentCreation($content1Data);
        $this->assertTrue($result1->isValid());
        $sanitized1 = $result1->getData();
        $this->assertIsArray($sanitized1);
        $this->assertIsArray($sanitized1['blocks']);
        $this->assertIsArray($sanitized1['blocks'][0]);
        $this->assertIsString($sanitized1['blocks'][0]['source']);
        $this->assertIsString($sanitized1['type']);
        $this->assertIsString($sanitized1['title']);

        $block1 = MarkdownBlock::create($sanitized1['blocks'][0]['source']);
        $content1 = ContentItem::create($sanitized1['type'], $sanitized1['title'], null, [$block1]);
        $this->repository->save($content1);

        // Create second content
        $result2 = $this->validationService->validateContentCreation($content2Data);
        $this->assertTrue($result2->isValid());
        $sanitized2 = $result2->getData();
        $this->assertIsArray($sanitized2);
        $this->assertIsArray($sanitized2['blocks']);
        $this->assertIsArray($sanitized2['blocks'][0]);
        $this->assertIsString($sanitized2['blocks'][0]['source']);
        $this->assertIsString($sanitized2['type']);
        $this->assertIsString($sanitized2['title']);

        $block2 = MarkdownBlock::create($sanitized2['blocks'][0]['source']);
        $content2 = ContentItem::create($sanitized2['type'], $sanitized2['title'], null, [$block2]);
        $this->repository->save($content2);

        // Rapid read operations
        for ($i = 0; $i < 10; ++$i) {
            $retrieved1 = $this->repository->findById($content1->id);
            $retrieved2 = $this->repository->findById($content2->id);

            $this->assertNotNull($retrieved1);
            $this->assertNotNull($retrieved2);
            $this->assertEquals('Concurrent Test 1', $retrieved1->title);
            $this->assertEquals('Concurrent Test 2', $retrieved2->title);
        }

        // Update operations
        $updatedContent1 = $content1->withTitle('Updated Concurrent Test 1');
        $updatedContent2 = $content2->withTitle('Updated Concurrent Test 2');

        $this->repository->save($updatedContent1);
        $this->repository->save($updatedContent2);

        // Verify updates
        $finalRetrieved1 = $this->repository->findById($content1->id);
        $finalRetrieved2 = $this->repository->findById($content2->id);

        $this->assertNotNull($finalRetrieved1);
        $this->assertNotNull($finalRetrieved2);
        $this->assertEquals('Updated Concurrent Test 1', $finalRetrieved1->title);
        $this->assertEquals('Updated Concurrent Test 2', $finalRetrieved2->title);
    }
}
