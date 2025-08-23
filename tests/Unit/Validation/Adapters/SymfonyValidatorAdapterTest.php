<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation\Adapters;

use PortableContent\Block\Markdown\MarkdownBlockValidator;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Validation\BlockValidatorManager;
use Symfony\Component\Validator\Validation;

/**
 * @internal
 */
final class SymfonyValidatorAdapterTest extends TestCase
{
    private SymfonyValidatorAdapter $validator;
    private SymfonyValidatorAdapter $validatorWithBlockRegistry;

    protected function setUp(): void
    {
        parent::setUp();

        $symfonyValidator = Validation::createValidator();
        $this->validator = new SymfonyValidatorAdapter($symfonyValidator);

        // Create validator with block manager for enhanced testing
        $blockManager = new BlockValidatorManager([
            new MarkdownBlockValidator(),
        ]);
        $this->validatorWithBlockRegistry = new SymfonyValidatorAdapter($symfonyValidator, $blockManager);
    }

    public function testValidateContentCreationWithValidData(): void
    {
        $validData = [
            'type' => 'note',
            'title' => 'Test Note',
            'summary' => 'A test note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World\n\nThis is a test.',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($validData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateContentCreationWithBlockRegistry(): void
    {
        $validData = [
            'type' => 'note',
            'title' => 'Test Note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World\n\nThis is a test.',
                ],
            ],
        ];

        $result = $this->validatorWithBlockRegistry->validateContentCreation($validData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateContentCreationWithMissingType(): void
    {
        $invalidData = [
            'title' => 'Test Note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('type'));
        $this->assertContains('Type is required', $result->getFieldErrors('type'));
    }

    public function testValidateContentCreationWithInvalidTypeCharacters(): void
    {
        $invalidData = [
            'type' => 'note-with-dashes',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('type'));
        $this->assertContains('Type must contain only letters, numbers, and underscores', $result->getFieldErrors('type'));
    }

    public function testValidateContentCreationWithTooLongTitle(): void
    {
        $invalidData = [
            'type' => 'note',
            'title' => str_repeat('x', 256), // Too long
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('title'));
        $this->assertContains('Title must be 255 characters or less', $result->getFieldErrors('title'));
    }

    public function testValidateContentCreationWithTooLongSummary(): void
    {
        $invalidData = [
            'type' => 'note',
            'summary' => str_repeat('x', 1001), // Too long
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('summary'));
        $this->assertContains('Summary must be 1000 characters or less', $result->getFieldErrors('summary'));
    }

    public function testValidateContentCreationWithEmptyBlocks(): void
    {
        $invalidData = [
            'type' => 'note',
            'title' => 'Test Note',
            'blocks' => [],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('blocks'));
        $this->assertContains('At least one block is required', $result->getFieldErrors('blocks'));
    }

    public function testValidateContentCreationWithTooManyBlocks(): void
    {
        $blocks = [];
        for ($i = 0; $i < 11; ++$i) {
            $blocks[] = [
                'kind' => 'markdown',
                'source' => "# Block {$i}",
            ];
        }

        $invalidData = [
            'type' => 'note',
            'blocks' => $blocks,
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('blocks'));
        $this->assertContains('Maximum 10 blocks allowed', $result->getFieldErrors('blocks'));
    }

    public function testValidateContentCreationWithInvalidBlockKind(): void
    {
        $invalidData = [
            'type' => 'note',
            'blocks' => [
                [
                    'kind' => 'invalid',
                    'source' => '# Hello World',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('blocks'));

        // Debug: Let's see what errors we actually get
        $blockErrors = $result->getFieldErrors('blocks');
        $this->assertNotEmpty($blockErrors);

        // Look for the error message in any of the block errors
        $found = false;
        foreach ($blockErrors as $error) {
            if (str_contains($error, 'kind must be "markdown"')) {
                $found = true;

                break;
            }
        }
        $this->assertTrue($found, 'Expected error message not found. Actual errors: '.implode(', ', $blockErrors));
    }

    public function testValidateContentCreationWithEmptyBlockSource(): void
    {
        $invalidData = [
            'type' => 'note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('blocks'));

        $blockErrors = $result->getFieldErrors('blocks');
        $found = false;
        foreach ($blockErrors as $error) {
            if (str_contains($error, 'source is required')) {
                $found = true;

                break;
            }
        }
        $this->assertTrue($found, 'Expected error message not found. Actual errors: '.implode(', ', $blockErrors));
    }

    public function testValidateContentCreationWithScriptTag(): void
    {
        $invalidData = [
            'type' => 'note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello <script>alert("xss")</script>',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('blocks'));

        $blockErrors = $result->getFieldErrors('blocks');
        $found = false;
        foreach ($blockErrors as $error) {
            if (str_contains($error, 'Script tags are not allowed in markdown content')) {
                $found = true;

                break;
            }
        }
        $this->assertTrue($found, 'Expected error message not found. Actual errors: '.implode(', ', $blockErrors));
    }

    public function testValidateContentCreationWithInvalidUtf8(): void
    {
        $invalidData = [
            'type' => 'note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => "# Hello \xFF\xFE Invalid UTF-8",
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('blocks'));

        $blockErrors = $result->getFieldErrors('blocks');
        $found = false;
        foreach ($blockErrors as $error) {
            if (str_contains($error, 'Content must be valid UTF-8 encoded text')) {
                $found = true;

                break;
            }
        }
        $this->assertTrue($found, 'Expected error message not found. Actual errors: '.implode(', ', $blockErrors));
    }

    public function testValidateContentUpdateAllowsPartialData(): void
    {
        $partialData = [
            'title' => 'Updated Title',
        ];

        $result = $this->validator->validateContentUpdate($partialData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateContentUpdateWithBlocks(): void
    {
        $updateData = [
            'title' => 'Updated Title',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Updated Content',
                ],
            ],
        ];

        $result = $this->validator->validateContentUpdate($updateData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateContentUpdateWithInvalidData(): void
    {
        $invalidData = [
            'type' => 'invalid-type!',
            'title' => str_repeat('x', 300),
        ];

        $result = $this->validator->validateContentUpdate($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('type'));
        $this->assertTrue($result->hasFieldErrors('title'));
    }

    public function testMultipleValidationErrors(): void
    {
        $invalidData = [
            'type' => '',
            'title' => str_repeat('x', 300),
            'summary' => str_repeat('x', 1100),
            'blocks' => [],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('type'));
        $this->assertTrue($result->hasFieldErrors('title'));
        $this->assertTrue($result->hasFieldErrors('summary'));
        $this->assertTrue($result->hasFieldErrors('blocks'));

        $this->assertContains('Type is required', $result->getFieldErrors('type'));
        $this->assertContains('Title must be 255 characters or less', $result->getFieldErrors('title'));
        $this->assertContains('Summary must be 1000 characters or less', $result->getFieldErrors('summary'));
        $this->assertContains('At least one block is required', $result->getFieldErrors('blocks'));
    }

    public function testValidateContentCreationWithExtraFields(): void
    {
        $invalidData = [
            'type' => 'note',
            'title' => 'Test Note',
            'extraField' => 'not allowed',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('general'));
        $this->assertContains("Field 'extraField' is not allowed", $result->getFieldErrors('general'));
    }

    public function testValidateContentCreationWithOptionalFields(): void
    {
        $validData = [
            'type' => 'note',
            // title and summary are optional
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World',
                ],
            ],
        ];

        $result = $this->validator->validateContentCreation($validData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }
}
