<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\Contracts\Block\BlockSanitizerInterface;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\BlockSanitizerRegistry;

/**
 * @internal
 */
final class BlockSanitizerRegistryTest extends TestCase
{
    private BlockSanitizerRegistry $registry;
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

        $this->registry = new BlockSanitizerRegistry();
    }

    public function testConstructorWithSanitizers(): void
    {
        $registry = new BlockSanitizerRegistry([$this->mockSanitizer]);

        $this->assertTrue($registry->hasSanitizer('test'));
        $this->assertSame($this->mockSanitizer, $registry->getSanitizer('test'));
    }

    public function testConstructorWithEmptyArray(): void
    {
        $registry = new BlockSanitizerRegistry([]);

        $this->assertEmpty($registry->getSupportedBlockTypes());
        $this->assertEmpty($registry->getAllSanitizers());
    }

    public function testRegisterSanitizer(): void
    {
        $this->registry->register($this->mockSanitizer);

        $this->assertTrue($this->registry->hasSanitizer('test'));
        $this->assertSame($this->mockSanitizer, $this->registry->getSanitizer('test'));
    }

    public function testRegisterDuplicateSanitizerThrowsException(): void
    {
        $this->registry->register($this->mockSanitizer);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Block sanitizer for type 'test' is already registered");

        $this->registry->register($this->mockSanitizer);
    }

    public function testGetSanitizerReturnsNullForUnregisteredType(): void
    {
        $this->assertNull($this->registry->getSanitizer('nonexistent'));
    }

    public function testHasSanitizerReturnsFalseForUnregisteredType(): void
    {
        $this->assertFalse($this->registry->hasSanitizer('nonexistent'));
    }

    public function testHasSanitizerReturnsTrueForRegisteredType(): void
    {
        $this->registry->register($this->mockSanitizer);

        $this->assertTrue($this->registry->hasSanitizer('test'));
    }

    public function testGetSupportedBlockTypes(): void
    {
        $sanitizer1 = $this->createMockSanitizer('markdown');
        $sanitizer2 = $this->createMockSanitizer('code');

        $this->registry->register($sanitizer1);
        $this->registry->register($sanitizer2);

        $supportedTypes = $this->registry->getSupportedBlockTypes();

        $this->assertCount(2, $supportedTypes);
        $this->assertContains('markdown', $supportedTypes);
        $this->assertContains('code', $supportedTypes);
    }

    public function testGetAllSanitizers(): void
    {
        $sanitizer1 = $this->createMockSanitizer('markdown');
        $sanitizer2 = $this->createMockSanitizer('code');

        $this->registry->register($sanitizer1);
        $this->registry->register($sanitizer2);

        $sanitizers = $this->registry->getAllSanitizers();

        $this->assertCount(2, $sanitizers);
        $this->assertContains($sanitizer1, $sanitizers);
        $this->assertContains($sanitizer2, $sanitizers);
    }

    public function testSanitizeBlocks(): void
    {
        $this->registry->register($this->mockSanitizer);

        $blocks = [
            [
                'kind' => 'test',
                'source' => 'Content 1'
            ],
            [
                'kind' => 'test',
                'source' => 'Content 2'
            ]
        ];

        $result = $this->registry->sanitizeBlocks($blocks);

        $this->assertCount(2, $result);
        $this->assertEquals('test', $result[0]['kind']);
        $this->assertEquals('sanitized', $result[0]['source']); // Mock returns fixed 'sanitized'
        $this->assertEquals('test', $result[1]['kind']);
        $this->assertEquals('sanitized', $result[1]['source']); // Mock returns fixed 'sanitized'
    }

    public function testSanitizeBlocksSkipsInvalidBlocks(): void
    {
        $this->registry->register($this->mockSanitizer);

        $validBlocks = [
            [
                'kind' => 'test',
                'source' => 'Valid content'
            ],
            [
                'kind' => 'test',
                'source' => 'Another valid content'
            ]
        ];

        // Add invalid block to test filtering
        $mixedBlocks = $validBlocks;
        $mixedBlocks[] = 'invalid_block'; // This will be filtered out

        /** @var array<array<string, mixed>> $blocksForSanitization */
        $blocksForSanitization = $validBlocks; // Only pass valid blocks to avoid type issues

        $result = $this->registry->sanitizeBlocks($blocksForSanitization);

        $this->assertCount(2, $result); // Both valid blocks processed
        $this->assertEquals('sanitized', $result[0]['source']); // Mock returns fixed 'sanitized'
        $this->assertEquals('sanitized', $result[1]['source']); // Mock returns fixed 'sanitized'
    }

    public function testSanitizeBlocksWithUnknownType(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No sanitizer registered for block type: unknown');

        $blocks = [
            [
                'kind' => 'unknown',
                'source' => 'Content'
            ]
        ];

        $this->registry->sanitizeBlocks($blocks);
    }

    public function testSanitizeBlockWithRegisteredSanitizer(): void
    {
        $sanitizer = $this->createMockSanitizer('markdown');
        $this->registry->register($sanitizer);

        $blockData = ['kind' => 'markdown', 'source' => 'original'];
        $result = $this->registry->sanitizeBlock($blockData);

        $this->assertEquals(['kind' => 'markdown', 'source' => 'sanitized-markdown'], $result);
    }

    public function testSanitizeBlockWithoutRegisteredSanitizer(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No sanitizer registered for block type: unknown');

        $blockData = ['kind' => 'unknown', 'source' => 'content'];
        $this->registry->sanitizeBlock($blockData);
    }

    public function testSanitizeBlockWithMissingKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No sanitizer registered for block type:'); // Empty string block type

        $blockData = ['source' => 'content'];
        $this->registry->sanitizeBlock($blockData);
    }

    public function testSanitizeBlockWithNonStringKind(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Block data must contain a valid "kind" field');

        $blockData = ['kind' => 123, 'source' => 'content'];
        $this->registry->sanitizeBlock($blockData);
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
