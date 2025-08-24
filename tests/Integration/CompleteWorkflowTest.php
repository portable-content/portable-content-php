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
 * End-to-end integration tests for the complete content workflow.
 *
 * Tests the full pipeline: Raw Input → Sanitization → Validation → Domain Objects → Repository → Database
 *
 * @internal
 */
final class CompleteWorkflowTest extends TestCase
{
    private ContentRepositoryInterface $repository;
    private ContentValidationService $validationService;

    protected function setUp(): void
    {
        parent::setUp();

        // Create in-memory repository for testing
        $this->repository = RepositoryFactory::createInMemoryRepository();

        // Create complete validation service with all components
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

    public function testCompleteContentCreationWorkflow(): void
    {
        // Step 1: Raw input data (as it would come from API/form)
        $rawInputData = [
            'type' => '  note  ', // Has whitespace that needs sanitization
            'title' => '  My First Note  ', // Has whitespace
            'summary' => "A test note\r\nwith\n\n\n\nmultiple lines", // Has mixed line endings and excessive newlines
            'blocks' => [
                [
                    'kind' => '  MARKDOWN  ', // Wrong case and whitespace
                    'source' => "  # Hello World  \n\n\n\n  This is my **first** note!  \n\n  - Item 1  \n  - Item 2  ",
                ],
            ],
        ];

        // Step 2: Validate and sanitize input
        $validationResult = $this->validationService->validateContentCreation($rawInputData);

        $this->assertTrue($validationResult->isValid(), 'Validation should pass for valid input');

        $sanitizedData = $validationResult->getData();
        $this->assertIsArray($sanitizedData);

        // Verify sanitization worked correctly
        $this->assertIsString($sanitizedData['type']);
        $this->assertEquals('note', $sanitizedData['type']);
        $this->assertIsString($sanitizedData['title']);
        $this->assertEquals('My First Note', $sanitizedData['title']);
        $this->assertIsString($sanitizedData['summary']);
        $this->assertEquals("A test note\nwith\n\nmultiple lines", $sanitizedData['summary']);

        $this->assertIsArray($sanitizedData['blocks']);
        $this->assertCount(1, $sanitizedData['blocks']);
        $this->assertIsArray($sanitizedData['blocks'][0]);
        $this->assertIsString($sanitizedData['blocks'][0]['kind']);
        $this->assertEquals('markdown', $sanitizedData['blocks'][0]['kind']);
        $this->assertIsString($sanitizedData['blocks'][0]['source']);
        $this->assertStringContainsString('# Hello World', $sanitizedData['blocks'][0]['source']);
        $this->assertStringContainsString('**first**', $sanitizedData['blocks'][0]['source']);

        // Step 3: Create domain objects from validated data
        $markdownBlock = MarkdownBlock::create($sanitizedData['blocks'][0]['source']);
        $contentItem = ContentItem::create(
            $sanitizedData['type'],
            $sanitizedData['title'],
            $sanitizedData['summary'],
            [$markdownBlock]
        );

        $this->assertEquals('note', $contentItem->getType());
        $this->assertEquals('My First Note', $contentItem->getTitle());
        $this->assertEquals("A test note\nwith\n\nmultiple lines", $contentItem->getSummary());
        $this->assertCount(1, $contentItem->getBlocks());

        // Step 4: Save to repository/database
        $this->repository->save($contentItem);

        // Step 5: Retrieve from database and verify persistence
        $retrievedContent = $this->repository->findById($contentItem->getId());

        $this->assertNotNull($retrievedContent);
        $this->assertEquals($contentItem->getId(), $retrievedContent->getId());
        $this->assertEquals($contentItem->getType(), $retrievedContent->getType());
        $this->assertEquals($contentItem->getTitle(), $retrievedContent->getTitle());
        $this->assertEquals($contentItem->getSummary(), $retrievedContent->getSummary());
        $this->assertCount(1, $retrievedContent->getBlocks());

        $retrievedBlock = $retrievedContent->getBlocks()[0];
        $this->assertInstanceOf(MarkdownBlock::class, $retrievedBlock);
        $this->assertEquals($markdownBlock->getContent(), $retrievedBlock->getContent());

        // Step 6: Update workflow
        $updateData = [
            'title' => '  Updated Title  ',
            'summary' => 'Updated summary',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Updated Content\n\nThis has been updated!',
                ],
            ],
        ];

        $updateValidationResult = $this->validationService->validateContentUpdate($updateData);
        $this->assertTrue($updateValidationResult->isValid());

        $updatedSanitizedData = $updateValidationResult->getData();
        $this->assertIsArray($updatedSanitizedData);
        $this->assertIsArray($updatedSanitizedData['blocks']);
        $this->assertIsArray($updatedSanitizedData['blocks'][0]);
        $this->assertIsString($updatedSanitizedData['blocks'][0]['source']);
        $this->assertIsString($updatedSanitizedData['title']);
        $this->assertIsString($updatedSanitizedData['summary']);

        $updatedBlock = MarkdownBlock::create($updatedSanitizedData['blocks'][0]['source']);
        $contentItem->setTitle($updatedSanitizedData['title']);
        $contentItem->setSummary($updatedSanitizedData['summary']);
        $contentItem->setBlocks([$updatedBlock]);

        $this->repository->save($contentItem);

        // Step 7: Verify update persisted
        $finalRetrievedContent = $this->repository->findById($contentItem->getId());

        $this->assertNotNull($finalRetrievedContent);
        $this->assertEquals('Updated Title', $finalRetrievedContent->getTitle());
        $this->assertEquals('Updated summary', $finalRetrievedContent->getSummary());
        $this->assertInstanceOf(MarkdownBlock::class, $finalRetrievedContent->getBlocks()[0]);
        /** @var MarkdownBlock $firstBlock */
        $firstBlock = $finalRetrievedContent->getBlocks()[0];
        $this->assertStringContainsString('Updated Content', $firstBlock->getContent());

        // Step 8: List all content
        $allContent = $this->repository->findAll();
        $this->assertCount(1, $allContent);
        $this->assertEquals($contentItem->getId(), $allContent[0]->getId());

        // Step 9: Delete workflow
        $this->repository->delete($contentItem->getId());

        $deletedContent = $this->repository->findById($contentItem->getId());
        $this->assertNull($deletedContent);

        $emptyList = $this->repository->findAll();
        $this->assertCount(0, $emptyList);
    }

    public function testCompleteWorkflowWithMultipleBlocks(): void
    {
        $rawInputData = [
            'type' => 'article',
            'title' => 'Multi-Block Article',
            'summary' => 'An article with multiple markdown blocks',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Introduction\n\nThis is the introduction.',
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Section 1\n\nFirst section content.',
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Section 2\n\nSecond section content.',
                ],
            ],
        ];

        // Validate and create content
        $validationResult = $this->validationService->validateContentCreation($rawInputData);
        $this->assertTrue($validationResult->isValid());

        $sanitizedData = $validationResult->getData();
        $this->assertIsArray($sanitizedData);
        $this->assertIsArray($sanitizedData['blocks']);

        $blocks = [];
        foreach ($sanitizedData['blocks'] as $blockData) {
            $this->assertIsArray($blockData);
            $this->assertIsString($blockData['source']);
            $blocks[] = MarkdownBlock::create($blockData['source']);
        }

        $this->assertIsString($sanitizedData['type']);
        $this->assertIsString($sanitizedData['title']);
        $this->assertIsString($sanitizedData['summary']);

        $contentItem = ContentItem::create(
            $sanitizedData['type'],
            $sanitizedData['title'],
            $sanitizedData['summary'],
            $blocks
        );

        // Save and retrieve
        $this->repository->save($contentItem);
        $retrievedContent = $this->repository->findById($contentItem->getId());

        $this->assertNotNull($retrievedContent);
        $this->assertCount(3, $retrievedContent->getBlocks());

        /** @var MarkdownBlock $block0 */
        $block0 = $retrievedContent->getBlocks()[0];
        /** @var MarkdownBlock $block1 */
        $block1 = $retrievedContent->getBlocks()[1];
        /** @var MarkdownBlock $block2 */
        $block2 = $retrievedContent->getBlocks()[2];

        $this->assertStringContainsString('Introduction', $block0->getContent());
        $this->assertStringContainsString('Section 1', $block1->getContent());
        $this->assertStringContainsString('Section 2', $block2->getContent());
    }

    public function testWorkflowWithValidationErrors(): void
    {
        // Test with invalid data that should fail validation
        $invalidData = [
            'type' => '', // Empty type should fail
            'title' => str_repeat('A', 300), // Too long title should fail
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '', // Empty source should fail
                ],
            ],
        ];

        $validationResult = $this->validationService->validateContentCreation($invalidData);

        $this->assertFalse($validationResult->isValid());
        $errors = $validationResult->getErrors();

        // Should have validation errors
        $this->assertNotEmpty($errors);

        // Should not be able to create content with invalid data
        // (This demonstrates the validation prevents invalid data from reaching the domain layer)
    }

    public function testWorkflowWithSanitizationErrors(): void
    {
        // Test with data that causes sanitization to fail
        $invalidData = [
            'type' => 'note',
            'title' => 'Valid Title',
            'blocks' => [
                [
                    'kind' => 'unknown_block_type', // This should cause sanitization to fail
                    'source' => 'Some content',
                ],
            ],
        ];

        $validationResult = $this->validationService->validateContentCreation($invalidData);

        $this->assertFalse($validationResult->isValid());
        $errors = $validationResult->getErrors();

        $this->assertArrayHasKey('sanitization', $errors);
        $this->assertStringContainsString('No sanitizer registered for block type: unknown_block_type', $errors['sanitization'][0]);
    }
}
