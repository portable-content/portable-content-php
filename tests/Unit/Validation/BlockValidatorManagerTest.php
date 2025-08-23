<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\Contracts\Block\BlockValidatorInterface;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\BlockValidatorManager;
use PortableContent\Validation\ValueObjects\ValidationResult;

/**
 * @internal
 */
final class BlockValidatorManagerTest extends TestCase
{
    private BlockValidatorManager $manager;
    private BlockValidatorInterface $mockValidator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockValidator = new class implements BlockValidatorInterface {
            public function validate(array $blockData): ValidationResult
            {
                return ValidationResult::success();
            }

            public function supports(string $blockType): bool
            {
                return 'test' === $blockType;
            }

            public function getBlockType(): string
            {
                return 'test';
            }
        };

        $this->manager = new BlockValidatorManager();
    }

    public function testConstructorWithValidators(): void
    {
        $manager = new BlockValidatorManager([$this->mockValidator]);

        $this->assertTrue($manager->hasValidator('test'));
        $this->assertSame($this->mockValidator, $manager->getValidator('test'));
    }

    public function testConstructorWithEmptyArray(): void
    {
        $manager = new BlockValidatorManager([]);

        $this->assertEmpty($manager->getSupportedBlockTypes());
        $this->assertEmpty($manager->getAllValidators());
    }

    public function testRegisterValidator(): void
    {
        $this->manager->register($this->mockValidator);

        $this->assertTrue($this->manager->hasValidator('test'));
        $this->assertSame($this->mockValidator, $this->manager->getValidator('test'));
    }

    public function testRegisterDuplicateValidatorThrowsException(): void
    {
        $this->manager->register($this->mockValidator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Block validator for type 'test' is already registered");

        $this->manager->register($this->mockValidator);
    }

    public function testGetValidatorReturnsNullForUnregisteredType(): void
    {
        $result = $this->manager->getValidator('nonexistent');
        $this->assertNull($result);
    }

    public function testHasValidatorReturnsFalseForUnregisteredType(): void
    {
        $this->assertFalse($this->manager->hasValidator('nonexistent'));
    }

    public function testHasValidatorReturnsTrueForRegisteredType(): void
    {
        $this->manager->register($this->mockValidator);

        $this->assertTrue($this->manager->hasValidator('test'));
    }

    public function testGetSupportedBlockTypes(): void
    {
        $validator1 = $this->createMockValidator('markdown');
        $validator2 = $this->createMockValidator('code');

        $this->manager->register($validator1);
        $this->manager->register($validator2);

        $supportedTypes = $this->manager->getSupportedBlockTypes();

        $this->assertCount(2, $supportedTypes);
        $this->assertContains('markdown', $supportedTypes);
        $this->assertContains('code', $supportedTypes);
    }

    public function testGetAllValidators(): void
    {
        $validator1 = $this->createMockValidator('markdown');
        $validator2 = $this->createMockValidator('code');

        $this->manager->register($validator1);
        $this->manager->register($validator2);

        $validators = $this->manager->getAllValidators();

        $this->assertCount(2, $validators);
        $this->assertContains($validator1, $validators);
        $this->assertContains($validator2, $validators);
    }

    public function testGetSupportedBlockTypesWithEmptyRegistry(): void
    {
        $this->assertEmpty($this->manager->getSupportedBlockTypes());
    }

    public function testGetAllValidatorsWithEmptyRegistry(): void
    {
        $this->assertEmpty($this->manager->getAllValidators());
    }

    public function testMultipleValidatorsWithDifferentTypes(): void
    {
        $markdownValidator = $this->createMockValidator('markdown');
        $codeValidator = $this->createMockValidator('code');
        $imageValidator = $this->createMockValidator('image');

        $this->manager->register($markdownValidator);
        $this->manager->register($codeValidator);
        $this->manager->register($imageValidator);

        $this->assertTrue($this->manager->hasValidator('markdown'));
        $this->assertTrue($this->manager->hasValidator('code'));
        $this->assertTrue($this->manager->hasValidator('image'));
        $this->assertFalse($this->manager->hasValidator('video'));

        $this->assertSame($markdownValidator, $this->manager->getValidator('markdown'));
        $this->assertSame($codeValidator, $this->manager->getValidator('code'));
        $this->assertSame($imageValidator, $this->manager->getValidator('image'));
    }

    private function createMockValidator(string $blockType): BlockValidatorInterface
    {
        return new class($blockType) implements BlockValidatorInterface {
            public function __construct(private string $blockType) {}

            public function validate(array $blockData): ValidationResult
            {
                return ValidationResult::success();
            }

            public function supports(string $blockType): bool
            {
                return $blockType === $this->blockType;
            }

            public function getBlockType(): string
            {
                return $this->blockType;
            }
        };
    }
}
