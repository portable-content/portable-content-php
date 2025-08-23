<?php

declare(strict_types=1);

namespace PortableContent\Validation\ValueObjects;

final class ValidationResult
{
    /**
     * @param array<string, string[]>   $errors Field name => array of error messages
     * @param null|array<string, mixed> $data   Optional data to include with the result
     */
    public function __construct(
        private readonly bool $isValid,
        private readonly array $errors = [],
        private readonly ?array $data = null
    ) {}

    public function isValid(): bool
    {
        return $this->isValid;
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get the data associated with this result.
     *
     * @return null|array<string, mixed>
     */
    public function getData(): ?array
    {
        return $this->data;
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
     * Get list of fields that have errors.
     *
     * @return string[]
     */
    public function getFieldsWithErrors(): array
    {
        return array_keys($this->errors);
    }

    /**
     * Check if there are any errors at all.
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Create a successful validation result.
     */
    public static function success(): self
    {
        return new self(true);
    }

    /**
     * Create a successful validation result with data.
     *
     * @param array<string, mixed> $data The data to include with the successful result
     */
    public static function successWithData(array $data): self
    {
        return new self(true, [], $data);
    }

    /**
     * Create a failed validation result.
     *
     * @param array<string, string[]> $errors
     */
    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }

    /**
     * Create a failed validation result with a single error.
     */
    public static function singleError(string $field, string $message): self
    {
        return new self(false, [$field => [$message]]);
    }

    /**
     * Merge this result with another validation result.
     */
    public function merge(ValidationResult $other): self
    {
        if ($this->isValid && $other->isValid) {
            return self::success();
        }

        $mergedErrors = $this->errors;
        foreach ($other->getErrors() as $field => $errors) {
            if (isset($mergedErrors[$field])) {
                $mergedErrors[$field] = array_merge($mergedErrors[$field], $errors);
            } else {
                $mergedErrors[$field] = $errors;
            }
        }

        return self::failure($mergedErrors);
    }

    /**
     * Convert to array representation.
     *
     * @return array{isValid: bool, errors: array<string, string[]>, errorCount: int, fieldsWithErrors: string[]}
     */
    public function toArray(): array
    {
        return [
            'isValid' => $this->isValid,
            'errors' => $this->errors,
            'errorCount' => $this->getErrorCount(),
            'fieldsWithErrors' => $this->getFieldsWithErrors(),
        ];
    }
}
