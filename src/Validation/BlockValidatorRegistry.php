<?php

declare(strict_types=1);

namespace PortableContent\Validation;

use PortableContent\Contracts\Block\BlockValidatorInterface;
use PortableContent\Exception\ValidationException;

final class BlockValidatorRegistry
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

    public function getValidator(string $blockType): BlockValidatorInterface
    {
        if (!isset($this->validators[$blockType])) {
            throw ValidationException::singleError(
                'blocks',
                "No validator registered for block type '{$blockType}'"
            );
        }

        return $this->validators[$blockType];
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
}
