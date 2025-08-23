<?php

declare(strict_types=1);

namespace PortableContent\Validation;

use PortableContent\Contracts\ContentValidatorInterface;
use PortableContent\Contracts\SanitizerInterface;
use PortableContent\Validation\ValueObjects\ValidationResult;

/**
 * Orchestrates content sanitization and validation processes.
 *
 * This service provides a unified interface for processing content through
 * the complete sanitization → validation pipeline, ensuring data is both
 * clean and valid before use.
 */
final class ContentValidationService implements ContentValidatorInterface
{
    public function __construct(
        private readonly SanitizerInterface $contentSanitizer,
        private readonly ContentValidatorInterface $contentValidator
    ) {}

    /**
     * Validate content for creation with full sanitization pipeline.
     *
     * Process:
     * 1. Sanitize the input data (content + blocks)
     * 2. Validate the sanitized data
     * 3. Return validation result with sanitized data if valid
     *
     * @param array<string, mixed> $data Raw content data to sanitize and validate
     *
     * @return ValidationResult Result containing sanitized data if valid, or errors if invalid
     */
    public function validateContentCreation(array $data): ValidationResult
    {
        try {
            // Step 1: Sanitize the input data
            $sanitizedData = $this->contentSanitizer->sanitize($data);

            // Step 2: Validate the sanitized data
            $validationResult = $this->contentValidator->validateContentCreation($sanitizedData);

            // Step 3: If validation passes, return result with sanitized data
            if ($validationResult->isValid()) {
                return ValidationResult::successWithData($sanitizedData);
            }

            // If validation fails, return the validation errors
            return $validationResult;
        } catch (\InvalidArgumentException $e) {
            // Handle sanitization errors (invalid block types, malformed data, etc.)
            return ValidationResult::singleError('sanitization', $e->getMessage());
        } catch (\Exception $e) {
            // Handle unexpected errors
            return ValidationResult::singleError('system', 'An unexpected error occurred during content processing');
        }
    }

    /**
     * Validate content for updates with full sanitization pipeline.
     *
     * Process:
     * 1. Sanitize the input data (content + blocks)
     * 2. Validate the sanitized data for updates
     * 3. Return validation result with sanitized data if valid
     *
     * @param array<string, mixed> $data Raw content data to sanitize and validate
     *
     * @return ValidationResult Result containing sanitized data if valid, or errors if invalid
     */
    public function validateContentUpdate(array $data): ValidationResult
    {
        try {
            // Step 1: Sanitize the input data
            $sanitizedData = $this->contentSanitizer->sanitize($data);

            // Step 2: Validate the sanitized data for updates
            $validationResult = $this->contentValidator->validateContentUpdate($sanitizedData);

            // Step 3: If validation passes, return result with sanitized data
            if ($validationResult->isValid()) {
                return ValidationResult::successWithData($sanitizedData);
            }

            // If validation fails, return the validation errors
            return $validationResult;
        } catch (\InvalidArgumentException $e) {
            // Handle sanitization errors (invalid block types, malformed data, etc.)
            return ValidationResult::singleError('sanitization', $e->getMessage());
        } catch (\Exception $e) {
            // Handle unexpected errors
            return ValidationResult::singleError('system', 'An unexpected error occurred during content processing');
        }
    }

    /**
     * Get the sanitized version of input data without validation.
     *
     * Useful for preview purposes or when you need clean data but
     * don't want to enforce validation rules.
     *
     * @param array<string, mixed> $data Raw content data to sanitize
     *
     * @return array<string, mixed> Sanitized content data
     *
     * @throws \InvalidArgumentException if sanitization fails
     */
    public function sanitizeContent(array $data): array
    {
        return $this->contentSanitizer->sanitize($data);
    }

    /**
     * Get sanitization statistics for monitoring and debugging.
     *
     * @param array<string, mixed> $originalData  Original input data
     * @param array<string, mixed> $sanitizedData Sanitized output data
     *
     * @return array<string, mixed> Statistics about the sanitization process
     */
    public function getSanitizationStats(array $originalData, array $sanitizedData): array
    {
        return $this->contentSanitizer->getSanitizationStats($originalData, $sanitizedData);
    }

    /**
     * Validate already-sanitized content.
     *
     * Use this when you have pre-sanitized data and only need validation.
     *
     * @param array<string, mixed> $sanitizedData Pre-sanitized content data
     *
     * @return ValidationResult Validation result without additional sanitization
     */
    public function validateSanitizedContent(array $sanitizedData): ValidationResult
    {
        return $this->contentValidator->validateContentCreation($sanitizedData);
    }

    /**
     * Process content through the complete pipeline and return both results.
     *
     * Useful for debugging and monitoring - provides detailed information
     * about both sanitization and validation steps.
     *
     * @param array<string, mixed> $data Raw content data to process
     *
     * @return array{
     *     sanitized_data: array<string, mixed>,
     *     sanitization_stats: array<string, mixed>,
     *     validation_result: ValidationResult,
     *     final_result: ValidationResult
     * } Complete processing results
     */
    public function processContentWithDetails(array $data): array
    {
        try {
            // Step 1: Sanitize
            $sanitizedData = $this->contentSanitizer->sanitize($data);
            $sanitizationStats = $this->contentSanitizer->getSanitizationStats($data, $sanitizedData);

            // Step 2: Validate
            $validationResult = $this->contentValidator->validateContentCreation($sanitizedData);

            // Step 3: Create final result
            $finalResult = $validationResult->isValid()
                ? ValidationResult::successWithData($sanitizedData)
                : $validationResult;

            return [
                'sanitized_data' => $sanitizedData,
                'sanitization_stats' => $sanitizationStats,
                'validation_result' => $validationResult,
                'final_result' => $finalResult,
            ];
        } catch (\InvalidArgumentException $e) {
            $errorResult = ValidationResult::singleError('sanitization', $e->getMessage());

            return [
                'sanitized_data' => [],
                'sanitization_stats' => [],
                'validation_result' => $errorResult,
                'final_result' => $errorResult,
            ];
        }
    }
}
