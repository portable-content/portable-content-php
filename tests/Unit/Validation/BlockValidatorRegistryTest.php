<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\Contracts\Block\BlockValidatorInterface;
use PortableContent\Exception\ValidationException;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\BlockValidatorRegistry;
use PortableContent\Validation\ValueObjects\ValidationResult;

/**
 * @internal
 *
 * @coversNothing
 */
final class BlockValidatorRegistryTest extends TestCase
{
    private BlockValidatorRegistry $registry;
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

        $this->registry = new BlockValidatorRegistry();
    }

    public function testConstructorWithValidators(): void
    {
        $registry = new BlockValidatorRegistry([$this->mockValidator]);

        $this->assertTrue($registry->hasValidator('test'));
        $this->assertSame($this->mockValidator, $registry->getValidator('test'));
    }

    public function testConstructorWithEmptyArray(): void
    {
        $registry = new BlockValidatorRegistry([]);

        $this->assertEmpty($registry->getSupportedBlockTypes());
        $this->assertEmpty($registry->getAllValidators());
    }

    public function testRegisterValidator(): void
    {
        $this->registry->register($this->mockValidator);

        $this->assertTrue($this->registry->hasValidator('test'));
        $this->assertSame($this->mockValidator, $this->registry->getValidator('test'));
    }

    public function testRegisterDuplicateValidatorThrowsException(): void
    {
        $this->registry->register($this->mockValidator);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Block validator for type 'test' is already registered");

        $this->registry->register($this->mockValidator);
    }

    public function testGetValidatorThrowsExceptionForUnregisteredType(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage("No validator registered for block type 'nonexistent'");

        $this->registry->getValidator('nonexistent');
    }

    public function testHasValidatorReturnsFalseForUnregisteredType(): void
    {
        $this->assertFalse($this->registry->hasValidator('nonexistent'));
    }

    public function testHasValidatorReturnsTrueForRegisteredType(): void
    {
        $this->registry->register($this->mockValidator);

        $this->assertTrue($this->registry->hasValidator('test'));
    }

    public function testGetSupportedBlockTypes(): void
    {
        $validator1 = $this->createMockValidator('markdown');
        $validator2 = $this->createMockValidator('code');

        $this->registry->register($validator1);
        $this->registry->register($validator2);

        $supportedTypes = $this->registry->getSupportedBlockTypes();

        $this->assertCount(2, $supportedTypes);
        $this->assertContains('markdown', $supportedTypes);
        $this->assertContains('code', $supportedTypes);
    }

    public function testGetAllValidators(): void
    {
        $validator1 = $this->createMockValidator('markdown');
        $validator2 = $this->createMockValidator('code');

        $this->registry->register($validator1);
        $this->registry->register($validator2);

        $validators = $this->registry->getAllValidators();

        $this->assertCount(2, $validators);
        $this->assertContains($validator1, $validators);
        $this->assertContains($validator2, $validators);
    }

    public function testGetSupportedBlockTypesWithEmptyRegistry(): void
    {
        $this->assertEmpty($this->registry->getSupportedBlockTypes());
    }

    public function testGetAllValidatorsWithEmptyRegistry(): void
    {
        $this->assertEmpty($this->registry->getAllValidators());
    }

    public function testMultipleValidatorsWithDifferentTypes(): void
    {
        $markdownValidator = $this->createMockValidator('markdown');
        $codeValidator = $this->createMockValidator('code');
        $imageValidator = $this->createMockValidator('image');

        $this->registry->register($markdownValidator);
        $this->registry->register($codeValidator);
        $this->registry->register($imageValidator);

        $this->assertTrue($this->registry->hasValidator('markdown'));
        $this->assertTrue($this->registry->hasValidator('code'));
        $this->assertTrue($this->registry->hasValidator('image'));
        $this->assertFalse($this->registry->hasValidator('video'));

        $this->assertSame($markdownValidator, $this->registry->getValidator('markdown'));
        $this->assertSame($codeValidator, $this->registry->getValidator('code'));
        $this->assertSame($imageValidator, $this->registry->getValidator('image'));
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
