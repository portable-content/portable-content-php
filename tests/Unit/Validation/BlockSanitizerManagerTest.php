<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\Contracts\Block\BlockSanitizerInterface;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\BlockSanitizerManager;

/**
 * @internal
 *
 */
final class BlockSanitizerManagerTest extends TestCase
{
    private BlockSanitizerManager $manager;
    private BlockSanitizerInterface $mockSanitizer;

    protected function setUp(): void
    {
        parent::setUp();

        $this->mockSanitizer = new class implements BlockSanitizerInterface {
            public function sanitize(array $blockData): array
            {
                return ['kind' => 'test', 'source' => 'sanitized'];
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

        $this->manager = new BlockSanitizerManager();
    }

    public function testConstructorWithSanitizers(): void
    {
        $manager = new BlockSanitizerManager([$this->mockSanitizer]);

        $this->assertTrue($manager->hasSanitizer('test'));
        $this->assertSame($this->mockSanitizer, $manager->getSanitizer('test'));
    }

    public function testConstructorWithEmptyArray(): void
    {
        $manager = new BlockSanitizerManager([]);

        $this->assertEmpty($manager->getSupportedBlockTypes());
        $this->assertEmpty($manager->getAllSanitizers());
    }

    public function testRegisterSanitizer(): void
    {
        $this->manager->register($this->mockSanitizer);

        $this->assertTrue($this->manager->hasSanitizer('test'));
        $this->assertSame($this->mockSanitizer, $this->manager->getSanitizer('test'));
    }

    public function testRegisterDuplicateSanitizerThrowsException(): void
    {
        $this->manager->register($this->mockSanitizer);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Block sanitizer for type 'test' is already registered");

        $this->manager->register($this->mockSanitizer);
    }

    public function testGetSanitizerReturnsNullForUnregisteredType(): void
    {
        $this->assertNull($this->manager->getSanitizer('nonexistent'));
    }

    public function testHasSanitizerReturnsFalseForUnregisteredType(): void
    {
        $this->assertFalse($this->manager->hasSanitizer('nonexistent'));
    }

    public function testHasSanitizerReturnsTrueForRegisteredType(): void
    {
        $this->manager->register($this->mockSanitizer);

        $this->assertTrue($this->manager->hasSanitizer('test'));
    }

    public function testGetSupportedBlockTypes(): void
    {
        $sanitizer1 = $this->createMockSanitizer('markdown');
        $sanitizer2 = $this->createMockSanitizer('code');

        $this->manager->register($sanitizer1);
        $this->manager->register($sanitizer2);

        $supportedTypes = $this->manager->getSupportedBlockTypes();

        $this->assertCount(2, $supportedTypes);
        $this->assertContains('markdown', $supportedTypes);
        $this->assertContains('code', $supportedTypes);
    }

    public function testGetAllSanitizers(): void
    {
        $sanitizer1 = $this->createMockSanitizer('markdown');
        $sanitizer2 = $this->createMockSanitizer('code');

        $this->manager->register($sanitizer1);
        $this->manager->register($sanitizer2);

        $sanitizers = $this->manager->getAllSanitizers();

        $this->assertCount(2, $sanitizers);
        $this->assertContains($sanitizer1, $sanitizers);
        $this->assertContains($sanitizer2, $sanitizers);
    }

    public function testSanitizeBlocks(): void
    {
        $this->manager->register($this->mockSanitizer);

        $blocks = [
            [
                'kind' => 'test',
                'source' => 'Content 1',
            ],
            [
                'kind' => 'test',
                'source' => 'Content 2',
            ],
        ];

        $result = $this->manager->sanitizeBlocks($blocks);

        $this->assertCount(2, $result);
        $this->assertEquals('test', $result[0]['kind']);
        $this->assertEquals('sanitized', $result[0]['source']); // Mock returns fixed 'sanitized'
        $this->assertEquals('test', $result[1]['kind']);
        $this->assertEquals('sanitized', $result[1]['source']); // Mock returns fixed 'sanitized'
    }

    public function testSanitizeBlocksWithInvalidBlockData(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid block data at index 1: expected array, got string');

        $this->manager->register($this->mockSanitizer);

        $blocksWithInvalidData = [
            [
                'kind' => 'test',
                'source' => 'Valid content',
            ],
            'invalid_block', // This should cause an exception
            [
                'kind' => 'test',
                'source' => 'Another valid content',
            ],
        ];

        // @phpstan-ignore-next-line - Intentionally passing invalid data to test exception
        $this->manager->sanitizeBlocks($blocksWithInvalidData);
    }

    public function testSanitizeBlocksWithUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No sanitizer registered for block type: unknown');

        $blocks = [
            [
                'kind' => 'unknown',
                'source' => 'Content',
            ],
        ];

        $this->manager->sanitizeBlocks($blocks);
    }

    public function testSanitizeBlockWithRegisteredSanitizer(): void
    {
        $sanitizer = $this->createMockSanitizer('markdown');
        $this->manager->register($sanitizer);

        $blockData = ['kind' => 'markdown', 'source' => 'original'];
        $result = $this->manager->sanitizeBlock($blockData);

        $this->assertEquals(['kind' => 'markdown', 'source' => 'sanitized-markdown'], $result);
    }

    public function testSanitizeBlockWithoutRegisteredSanitizer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No sanitizer registered for block type: unknown');

        $blockData = ['kind' => 'unknown', 'source' => 'content'];
        $this->manager->sanitizeBlock($blockData);
    }

    public function testSanitizeBlockWithMissingKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No sanitizer registered for block type:'); // Empty string block type

        $blockData = ['source' => 'content'];
        $this->manager->sanitizeBlock($blockData);
    }

    public function testSanitizeBlockWithNonStringKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block data must contain a valid "kind" field');

        $blockData = ['kind' => 123, 'source' => 'content'];
        $this->manager->sanitizeBlock($blockData);
    }

    private function createMockSanitizer(string $blockType): BlockSanitizerInterface
    {
        return new class($blockType) implements BlockSanitizerInterface {
            public function __construct(private string $blockType) {}

            public function sanitize(array $blockData): array
            {
                return ['kind' => $this->blockType, 'source' => "sanitized-{$this->blockType}"];
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
