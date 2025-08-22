<?php

declare(strict_types=1);

namespace PortableContent\Exception;

final class ValidationException extends \InvalidArgumentException
{
    /**
     * @param array<string, string[]> $errors Field name => array of error messages
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed',
        int $code = 0,
        ?\Throwable $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get validation errors.
     *
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get errors for a specific field.
     *
     * @return string[]
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors.
     */
    public function hasFieldErrors(string $field): bool
    {
        return !empty($this->errors[$field]);
    }

    /**
     * Get all error messages as a flat array.
     *
     * @return string[]
     */
    public function getAllMessages(): array
    {
        $messages = [];
        foreach ($this->errors as $field => $fieldErrors) {
            foreach ($fieldErrors as $error) {
                $messages[] = "{$field}: {$error}";
            }
        }

        return $messages;
    }

    /**
     * Get total number of errors across all fields.
     */
    public function getErrorCount(): int
    {
        return array_sum(array_map('count', $this->errors));
    }

    /**
     * Create exception from a single field error.
     */
    public static function singleError(string $field, string $message): self
    {
        return new self([$field => [$message]], "Validation failed: {$field}: {$message}");
    }

    /**
     * Create exception with multiple errors.
     *
     * @param array<string, string[]> $errors
     */
    public static function multipleErrors(array $errors): self
    {
        $errorCount = array_sum(array_map('count', $errors));
        $message = "Validation failed with {$errorCount} error(s)";

        return new self($errors, $message);
    }
}
