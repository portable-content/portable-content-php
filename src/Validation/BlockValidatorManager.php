<?php

declare(strict_types=1);

namespace PortableContent\Validation;

use PortableContent\Contracts\Block\BlockValidatorInterface;
use PortableContent\Exception\ValidationException;

/**
 * Manager for coordinating block validators and validation processes.
 */
final class BlockValidatorManager
{
    /**
     * @var array<string, BlockValidatorInterface>
     */
    private array $validators = [];

    /**
     * @param BlockValidatorInterface[] $validators
     */
    public function __construct(array $validators = [])
    {
        foreach ($validators as $validator) {
            $this->register($validator);
        }
    }

    public function register(BlockValidatorInterface $validator): void
    {
        $blockType = $validator->getBlockType();

        if (isset($this->validators[$blockType])) {
            throw new \InvalidArgumentException(
                "Block validator for type '{$blockType}' is already registered"
            );
        }

        $this->validators[$blockType] = $validator;
    }

    public function getValidator(string $blockType): ?BlockValidatorInterface
    {
        return $this->validators[$blockType] ?? null;
    }

    public function hasValidator(string $blockType): bool
    {
        return isset($this->validators[$blockType]);
    }

    /**
     * Get all supported block types.
     *
     * @return string[]
     */
    public function getSupportedBlockTypes(): array
    {
        return array_keys($this->validators);
    }

    /**
     * Get all registered validators.
     *
     * @return BlockValidatorInterface[]
     */
    public function getAllValidators(): array
    {
        return array_values($this->validators);
    }

    /**
     * Validate multiple blocks using the appropriate validators.
     *
     * @param array<array<string, mixed>> $blocks Array of block data arrays
     *
     * @throws ValidationException       if validation fails for any block
     * @throws \InvalidArgumentException if a block type has no registered validator
     */
    public function validateBlocks(array $blocks): void
    {
        foreach ($blocks as $index => $blockData) {
            if (!is_array($blockData)) {
                throw new \InvalidArgumentException(
                    "Invalid block data at index {$index}: expected array, got ".gettype($blockData)
                );
            }

            $this->validateBlock($blockData);
        }
    }

    /**
     * Validate a single block using the appropriate validator.
     *
     * Block data structure:
     * - 'kind' (string, required): The block type (e.g., 'markdown', 'html', 'code')
     * - 'source' (string, required): The raw content of the block
     * - Additional fields may be present and will be passed through to the validator
     *
     * @param array<string, mixed> $blockData Block data containing 'kind' and 'source' fields
     *
     * @throws ValidationException       if validation fails
     * @throws \InvalidArgumentException if the block type has no registered validator
     */
    public function validateBlock(array $blockData): void
    {
        $blockType = $blockData['kind'] ?? '';

        if (!is_string($blockType) || trim($blockType) === '') {
            throw new \InvalidArgumentException('Block data must contain a valid "kind" field');
        }

        if (!$this->hasValidator($blockType)) {
            throw new \InvalidArgumentException("No validator registered for block type: {$blockType}");
        }

        $validator = $this->getValidator($blockType);
        if (null === $validator) {
            throw new \InvalidArgumentException("No validator registered for block type: {$blockType}");
        }

        $validator->validate($blockData);
    }
}
