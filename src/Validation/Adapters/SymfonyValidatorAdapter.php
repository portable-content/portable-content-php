<?php

declare(strict_types=1);

namespace PortableContent\Validation\Adapters;

use PortableContent\Contracts\ContentValidatorInterface;
use PortableContent\Validation\ValueObjects\ValidationResult;
use PortableContent\Validation\BlockValidatorRegistry;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class SymfonyValidatorAdapter implements ContentValidatorInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator,
        private readonly ?BlockValidatorRegistry $blockValidatorRegistry = null
    ) {}

    public function validateContentCreation(array $data): ValidationResult
    {
        // Use individual field validation instead of Collection constraint
        $errors = [];

        // Check for extra fields
        $allowedFields = ['type', 'title', 'summary', 'blocks'];
        foreach (array_keys($data) as $field) {
            if (!in_array($field, $allowedFields, true)) {
                $errors['general'][] = "Field '{$field}' is not allowed";
            }
        }

        // Validate type (required)
        if (!isset($data['type'])) {
            $errors['type'][] = 'Type is required';
        } else {
            $typeViolations = $this->validator->validate($data['type'], [
                new Assert\NotBlank(message: 'Type is required'),
                new Assert\Length(max: 50, maxMessage: 'Type must be 50 characters or less'),
                new Assert\Regex(
                    pattern: '/^[a-zA-Z0-9_]+$/',
                    message: 'Type must contain only letters, numbers, and underscores'
                ),
            ]);
            $this->addViolationsToErrors($typeViolations, 'type', $errors);
        }

        // Validate title (optional)
        if (isset($data['title'])) {
            $titleViolations = $this->validator->validate($data['title'], [
                new Assert\Length(max: 255, maxMessage: 'Title must be 255 characters or less'),
            ]);
            $this->addViolationsToErrors($titleViolations, 'title', $errors);
        }

        // Validate summary (optional)
        if (isset($data['summary'])) {
            $summaryViolations = $this->validator->validate($data['summary'], [
                new Assert\Length(max: 1000, maxMessage: 'Summary must be 1000 characters or less'),
            ]);
            $this->addViolationsToErrors($summaryViolations, 'summary', $errors);
        }

        // Validate blocks (required)
        if (!isset($data['blocks'])) {
            $errors['blocks'][] = 'At least one block is required';
        } else {
            $blocksViolations = $this->validator->validate($data['blocks'], [
                new Assert\NotBlank(message: 'At least one block is required'),
                new Assert\Type('array', message: 'Blocks must be an array'),
                new Assert\Count(
                    min: 1,
                    max: 10,
                    minMessage: 'At least one block is required',
                    maxMessage: 'Maximum 10 blocks allowed'
                ),
            ]);
            $this->addViolationsToErrors($blocksViolations, 'blocks', $errors);

            // Validate individual blocks
            if (is_array($data['blocks'])) {
                foreach ($data['blocks'] as $index => $blockData) {
                    if (is_array($blockData)) {
                        $blockErrors = $this->validateSingleBlock($blockData);
                        if (!empty($blockErrors)) {
                            foreach ($blockErrors as $field => $fieldErrors) {
                                foreach ($fieldErrors as $error) {
                                    $errors['blocks'][] = "Block {$index} {$field}: {$error}";
                                }
                            }
                        }
                    }
                }
            }
        }

        $result = empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);

        // If basic validation passes and we have block validators, validate blocks individually
        if ($result->isValid() && $this->blockValidatorRegistry !== null && isset($data['blocks']) && is_array($data['blocks'])) {
            $result = $this->validateBlocksWithRegistry($data['blocks'], $result);
        }

        return $result;
    }

    public function validateContentUpdate(array $data): ValidationResult
    {
        // For updates, all fields are optional
        $errors = [];

        // Validate type (optional)
        if (isset($data['type'])) {
            $typeViolations = $this->validator->validate($data['type'], [
                new Assert\Length(max: 50, maxMessage: 'Type must be 50 characters or less'),
                new Assert\Regex(
                    pattern: '/^[a-zA-Z0-9_]+$/',
                    message: 'Type must contain only letters, numbers, and underscores'
                ),
            ]);
            $this->addViolationsToErrors($typeViolations, 'type', $errors);
        }

        // Validate title (optional)
        if (isset($data['title'])) {
            $titleViolations = $this->validator->validate($data['title'], [
                new Assert\Length(max: 255, maxMessage: 'Title must be 255 characters or less'),
            ]);
            $this->addViolationsToErrors($titleViolations, 'title', $errors);
        }

        // Validate summary (optional)
        if (isset($data['summary'])) {
            $summaryViolations = $this->validator->validate($data['summary'], [
                new Assert\Length(max: 1000, maxMessage: 'Summary must be 1000 characters or less'),
            ]);
            $this->addViolationsToErrors($summaryViolations, 'summary', $errors);
        }

        // Validate blocks (optional)
        if (isset($data['blocks'])) {
            $blocksViolations = $this->validator->validate($data['blocks'], [
                new Assert\Type('array', message: 'Blocks must be an array'),
                new Assert\Count(
                    min: 1,
                    max: 10,
                    minMessage: 'At least one block is required',
                    maxMessage: 'Maximum 10 blocks allowed'
                ),
            ]);
            $this->addViolationsToErrors($blocksViolations, 'blocks', $errors);

            // Validate individual blocks
            if (is_array($data['blocks'])) {
                foreach ($data['blocks'] as $index => $blockData) {
                    if (is_array($blockData)) {
                        $blockErrors = $this->validateSingleBlock($blockData);
                        if (!empty($blockErrors)) {
                            foreach ($blockErrors as $field => $fieldErrors) {
                                foreach ($fieldErrors as $error) {
                                    $errors['blocks'][] = "Block {$index} {$field}: {$error}";
                                }
                            }
                        }
                    }
                }
            }
        }

        $result = empty($errors) ? ValidationResult::success() : ValidationResult::failure($errors);

        // If basic validation passes and we have block validators, validate blocks individually
        if ($result->isValid() && $this->blockValidatorRegistry !== null && isset($data['blocks']) && is_array($data['blocks'])) {
            $result = $this->validateBlocksWithRegistry($data['blocks'], $result);
        }

        return $result;
    }

    /**
     * Custom validation for markdown security
     */
    public function validateMarkdownSecurity(mixed $value, ExecutionContextInterface $context): void
    {
        if (!is_string($value)) {
            return;
        }

        // Check for script tags
        if (preg_match('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', $value)) {
            $context->buildViolation('Script tags are not allowed in markdown content')
                ->addViolation();
        }

        // Check UTF-8 encoding
        if (!mb_check_encoding($value, 'UTF-8')) {
            $context->buildViolation('Content must be valid UTF-8 encoded text')
                ->addViolation();
        }
    }

    /**
     * Validate blocks using the block validator registry
     *
     * @param array<array<string, mixed>> $blocks
     */
    private function validateBlocksWithRegistry(array $blocks, ValidationResult $currentResult): ValidationResult
    {
        foreach ($blocks as $index => $blockData) {
            if (!is_array($blockData)) {
                continue;
            }

            $blockType = $blockData['kind'] ?? '';
            if (!is_string($blockType)) {
                continue;
            }

            if ($this->blockValidatorRegistry !== null && $this->blockValidatorRegistry->hasValidator($blockType)) {
                try {
                    $blockValidator = $this->blockValidatorRegistry->getValidator($blockType);
                    $blockResult = $blockValidator->validate($blockData);

                    if (!$blockResult->isValid()) {
                        // Merge block validation errors with current result
                        $currentResult = $currentResult->merge($blockResult);
                    }
                } catch (\Exception $e) {
                    // If block validation fails, add a generic error
                    $errorResult = ValidationResult::singleError(
                        'blocks',
                        "Block {$index} validation failed: " . $e->getMessage()
                    );
                    $currentResult = $currentResult->merge($errorResult);
                }
            }
        }

        return $currentResult;
    }

    /**
     * Add violations to errors array
     *
     * @param array<string, string[]> $errors
     */
    private function addViolationsToErrors(ConstraintViolationListInterface $violations, string $fieldName, array &$errors): void
    {
        foreach ($violations as $violation) {
            if (!isset($errors[$fieldName])) {
                $errors[$fieldName] = [];
            }
            $errors[$fieldName][] = $violation->getMessage();
        }
    }

    /**
     * Validate a single block
     *
     * @param array<string, mixed> $blockData
     * @return array<string, string[]>
     */
    private function validateSingleBlock(array $blockData): array
    {
        $errors = [];

        // Validate kind
        if (!isset($blockData['kind'])) {
            $errors['kind'][] = 'kind is required';
        } else {
            $kindViolations = $this->validator->validate($blockData['kind'], [
                new Assert\NotBlank(message: 'kind is required'),
                new Assert\Choice(
                    choices: ['markdown'],
                    message: 'kind must be "markdown"'
                ),
            ]);
            $this->addViolationsToErrors($kindViolations, 'kind', $errors);
        }

        // Validate source
        if (!isset($blockData['source'])) {
            $errors['source'][] = 'source is required';
        } else {
            $sourceViolations = $this->validator->validate($blockData['source'], [
                new Assert\NotBlank(message: 'source is required'),
                new Assert\Length(
                    max: 100000,
                    maxMessage: 'source must be 100KB or less'
                ),
                new Assert\Callback([$this, 'validateMarkdownSecurity']),
            ]);
            $this->addViolationsToErrors($sourceViolations, 'source', $errors);
        }

        return $errors;
    }
}
