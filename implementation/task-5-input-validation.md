# Task 5: Input Validation - Detailed Steps

## Overview
Implement a flexible, adapter-based validation system for content creation and updates. This creates a robust validation layer with pluggable validation libraries, ensuring data integrity while giving users choice in their validation approach.

**Estimated Time:** 2-3 hours
**Dependencies:** Task 4 (Repository Pattern) must be completed

---

## Step 5.1: Design the Validation Architecture
**Time:** 15-20 minutes

### Instructions:
We'll implement an adapter-based validation system that supports multiple validation libraries while maintaining a clean, consistent interface.

**Architecture Principles:**
- **Dependency Injection**: Users inject their preferred validation adapter
- **Clean Interfaces**: Clear contracts in the Contracts/ folder
- **No Lock-in**: No factory pattern - users choose and configure their own adapters
- **Separation of Concerns**: Separate sanitization and validation services
- **Type Safety**: Use DTOs instead of arrays for validated data
- **Proven Libraries**: Use established validation libraries like Symfony Validator

**Content-Level Validation Rules:**
- `type`: Required, non-empty string, max 50 characters, alphanumeric + underscore
- `title`: Optional, max 255 characters if provided
- `summary`: Optional, max 1000 characters if provided
- `blocks`: Required, at least one block, all must be valid

**Block-Level Validation Rules:**
- `source`: Required, non-empty after trimming, max 100KB
- `kind`: Must be 'markdown' (for Phase 1A), extensible for future block types

**Validation Flow:**
```
Raw Input → Sanitization → Validation → DTO Creation → Domain Object Factory → ContentItem
```

### Validation:
- [ ] Architecture supports multiple validation libraries
- [ ] Clear separation between sanitization and validation
- [ ] Type-safe DTOs replace primitive arrays
- [ ] Extensible design for future block types
- [ ] Clean error handling with field-specific messages

---

## Step 5.2: Create Core Validation Contracts
**Time:** 15 minutes

### Instructions:
1. Create `src/Contracts/ContentValidatorInterface.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Contracts;

interface ContentValidatorInterface
{
    /**
     * Validate data for content creation
     */
    public function validateContentCreation(array $data): ValidationResult;

    /**
     * Validate data for content updates (allows partial data)
     */
    public function validateContentUpdate(array $data): ValidationResult;

    /**
     * Validate a single block
     */
    public function validateBlock(array $blockData): ValidationResult;
}
```

2. Create `src/Validation/ValueObjects/ValidationResult.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation\ValueObjects;

final class ValidationResult
{
    /**
     * @param array<string, string[]> $errors Field name => array of error messages
     */
    public function __construct(
        private readonly bool $isValid,
        private readonly array $errors = []
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
     * Get errors for a specific field
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors
     */
    public function hasFieldErrors(string $field): bool
    {
        return !empty($this->errors[$field]);
    }

    /**
     * Get all error messages as a flat array
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
     * Create a successful validation result
     */
    public static function success(): self
    {
        return new self(true);
    }

    /**
     * Create a failed validation result
     */
    public static function failure(array $errors): self
    {
        return new self(false, $errors);
    }
}
```

3. Create `src/Contracts/SanitizerInterface.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Contracts;

interface SanitizerInterface
{
    /**
     * Sanitize input data
     */
    public function sanitize(array $data): array;
}
```

### Key Features:
- **Clean contracts**: Interfaces define clear validation responsibilities
- **Immutable results**: ValidationResult is immutable and type-safe
- **Separation of concerns**: Sanitization is separate from validation
- **Factory methods**: Easy creation of success/failure results

### Validation:
- [ ] Core interfaces are created in Contracts/ folder
- [ ] ValidationResult provides comprehensive error handling
- [ ] Sanitization is separated from validation
- [ ] Interfaces support adapter pattern

---

## Step 5.3: Create Value Objects
**Time:** 15 minutes

### Instructions:
1. Create `src/Validation/ValueObjects/ContentCreationRequest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation\ValueObjects;

final class ContentCreationRequest
{
    /**
     * @param BlockData[] $blocks
     */
    public function __construct(
        public readonly string $type,
        public readonly ?string $title,
        public readonly ?string $summary,
        public readonly array $blocks
    ) {}

    /**
     * Create from validated array data
     */
    public static function fromArray(array $data): self
    {
        $blocks = [];
        foreach ($data['blocks'] as $blockData) {
            $blocks[] = BlockData::fromArray($blockData);
        }

        return new self(
            type: $data['type'],
            title: $data['title'] ?? null,
            summary: $data['summary'] ?? null,
            blocks: $blocks
        );
    }

    /**
     * Convert to array for backward compatibility
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'title' => $this->title,
            'summary' => $this->summary,
            'blocks' => array_map(fn(BlockData $block) => $block->toArray(), $this->blocks)
        ];
    }
}
```

2. Create `src/Validation/ValueObjects/BlockData.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation\ValueObjects;

final class BlockData
{
    public function __construct(
        public readonly string $kind,
        public readonly string $source
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            kind: $data['kind'],
            source: $data['source']
        );
    }

    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'source' => $this->source
        ];
    }
}
```

### Key Features:
- **Type safety**: Immutable value objects replace primitive arrays
- **Clear contracts**: Explicit structure for validation data
- **Factory methods**: Easy creation from array data
- **Backward compatibility**: Can convert back to arrays if needed

### Validation:
- [ ] Value objects are created for type-safe data handling
- [ ] Factory methods support array conversion
- [ ] Immutable design prevents data corruption
- [ ] Clear structure for validation pipeline

---

## Step 5.4: Create Symfony Validator Adapter
**Time:** 25-30 minutes

### Instructions:
1. First, add Symfony Validator to composer.json:
```bash
composer require symfony/validator
```

2. Create `src/Validation/Adapters/SymfonyValidatorAdapter.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation\Adapters;

use PortableContent\Contracts\ContentValidatorInterface;
use PortableContent\Validation\ValueObjects\ValidationResult;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class SymfonyValidatorAdapter implements ContentValidatorInterface
{
    public function __construct(
        private readonly ValidatorInterface $validator
    ) {}

    public function validateContentCreation(array $data): ValidationResult
    {
        $constraints = new Assert\Collection([
            'fields' => [
                'type' => [
                    new Assert\NotBlank(message: 'Type is required'),
                    new Assert\Length(max: 50, maxMessage: 'Type must be 50 characters or less'),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z0-9_]+$/',
                        message: 'Type must contain only letters, numbers, and underscores'
                    ),
                ],
                'title' => [
                    new Assert\Length(max: 255, maxMessage: 'Title must be 255 characters or less'),
                ],
                'summary' => [
                    new Assert\Length(max: 1000, maxMessage: 'Summary must be 1000 characters or less'),
                ],
                'blocks' => [
                    new Assert\NotBlank(message: 'At least one block is required'),
                    new Assert\Type('array', message: 'Blocks must be an array'),
                    new Assert\Count(
                        min: 1,
                        max: 10,
                        minMessage: 'At least one block is required',
                        maxMessage: 'Maximum 10 blocks allowed'
                    ),
                    new Assert\All([
                        new Assert\Collection([
                            'fields' => [
                                'kind' => [
                                    new Assert\NotBlank(message: 'Block kind is required'),
                                    new Assert\Choice(
                                        choices: ['markdown'],
                                        message: 'Block kind must be "markdown"'
                                    ),
                                ],
                                'source' => [
                                    new Assert\NotBlank(message: 'Block source is required'),
                                    new Assert\Length(
                                        max: 100000,
                                        maxMessage: 'Block source must be 100KB or less'
                                    ),
                                    new Assert\Callback([$this, 'validateMarkdownSecurity']),
                                ],
                            ],
                            'allowExtraFields' => false,
                        ]),
                    ]),
                ],
            ],
            'allowExtraFields' => false,
            'allowMissingFields' => ['title', 'summary'], // These are optional
        ]);

        return $this->validateWithConstraints($data, $constraints);
    }

    public function validateContentUpdate(array $data): ValidationResult
    {
        // For updates, all fields are optional
        $constraints = new Assert\Collection([
            'fields' => [
                'type' => [
                    new Assert\Length(max: 50, maxMessage: 'Type must be 50 characters or less'),
                    new Assert\Regex(
                        pattern: '/^[a-zA-Z0-9_]+$/',
                        message: 'Type must contain only letters, numbers, and underscores'
                    ),
                ],
                'title' => [
                    new Assert\Length(max: 255, maxMessage: 'Title must be 255 characters or less'),
                ],
                'summary' => [
                    new Assert\Length(max: 1000, maxMessage: 'Summary must be 1000 characters or less'),
                ],
                'blocks' => [
                    new Assert\Type('array', message: 'Blocks must be an array'),
                    new Assert\Count(
                        min: 1,
                        max: 10,
                        minMessage: 'At least one block is required',
                        maxMessage: 'Maximum 10 blocks allowed'
                    ),
                    new Assert\All([
                        new Assert\Collection([
                            'fields' => [
                                'kind' => [
                                    new Assert\NotBlank(message: 'Block kind is required'),
                                    new Assert\Choice(
                                        choices: ['markdown'],
                                        message: 'Block kind must be "markdown"'
                                    ),
                                ],
                                'source' => [
                                    new Assert\NotBlank(message: 'Block source is required'),
                                    new Assert\Length(
                                        max: 100000,
                                        maxMessage: 'Block source must be 100KB or less'
                                    ),
                                    new Assert\Callback([$this, 'validateMarkdownSecurity']),
                                ],
                            ],
                            'allowExtraFields' => false,
                        ]),
                    ]),
                ],
            ],
            'allowExtraFields' => false,
            'allowMissingFields' => ['type', 'title', 'summary', 'blocks'], // All optional for updates
        ]);

        return $this->validateWithConstraints($data, $constraints);
    }

    public function validateBlock(array $blockData): ValidationResult
    {
        $constraints = new Assert\Collection([
            'fields' => [
                'kind' => [
                    new Assert\NotBlank(message: 'Block kind is required'),
                    new Assert\Choice(
                        choices: ['markdown'],
                        message: 'Block kind must be "markdown"'
                    ),
                ],
                'source' => [
                    new Assert\NotBlank(message: 'Block source is required'),
                    new Assert\Length(
                        max: 100000,
                        maxMessage: 'Block source must be 100KB or less'
                    ),
                    new Assert\Callback([$this, 'validateMarkdownSecurity']),
                ],
            ],
            'allowExtraFields' => false,
        ]);

        return $this->validateWithConstraints($blockData, $constraints);
    }

    /**
     * Custom validation for markdown security
     */
    public function validateMarkdownSecurity($value, $context): void
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

    private function validateWithConstraints(array $data, Assert\Collection $constraints): ValidationResult
    {
        $violations = $this->validator->validate($data, $constraints);

        if (count($violations) === 0) {
            return ValidationResult::success();
        }

        $errors = [];
        foreach ($violations as $violation) {
            $propertyPath = $violation->getPropertyPath();
            $message = $violation->getMessage();

            // Convert property path to field name (e.g., "[blocks][0][source]" -> "blocks")
            $fieldName = $this->extractFieldName($propertyPath);

            if (!isset($errors[$fieldName])) {
                $errors[$fieldName] = [];
            }
            $errors[$fieldName][] = $message;
        }

        return ValidationResult::failure($errors);
    }

    private function extractFieldName(string $propertyPath): string
    {
        // Extract the main field name from property path
        if (preg_match('/^\[([^\]]+)\]/', $propertyPath, $matches)) {
            return $matches[1];
        }

        return $propertyPath ?: 'general';
    }
}
```

### Key Features:
- **Proven validation library**: Uses Symfony Validator with robust constraint system
- **Declarative validation**: Clear, readable constraint definitions
- **Security checks**: Custom validation for script injection and encoding issues
- **Flexible requirements**: Different validation for creation vs updates
- **Professional error messages**: Leverages Symfony's mature error handling

### Validation:
- [ ] Symfony validator adapter is created
- [ ] Implements ContentValidatorInterface
- [ ] Uses Symfony constraints for robust validation
- [ ] Handles creation and update validation differently
- [ ] Includes custom security validation callbacks

---

## Step 5.5: Create Sanitizer Service
**Time:** 15 minutes

### Instructions:
1. Create `src/Validation/ContentSanitizer.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation;

use PortableContent\Contracts\SanitizerInterface;

final class ContentSanitizer implements SanitizerInterface
{
    public function sanitize(array $data): array
    {
        $sanitized = [];

        if (isset($data['type'])) {
            $sanitized['type'] = trim((string) $data['type']);
        }

        if (isset($data['title'])) {
            $title = trim((string) $data['title']);
            $sanitized['title'] = $title !== '' ? $title : null;
        }

        if (isset($data['summary'])) {
            $summary = trim((string) $data['summary']);
            $sanitized['summary'] = $summary !== '' ? $summary : null;
        }

        if (isset($data['blocks']) && is_array($data['blocks'])) {
            $sanitized['blocks'] = [];
            foreach ($data['blocks'] as $block) {
                if (is_array($block)) {
                    $sanitizedBlock = [];
                    if (isset($block['kind'])) {
                        $sanitizedBlock['kind'] = trim((string) $block['kind']);
                    }
                    if (isset($block['source'])) {
                        // Don't trim source content as whitespace might be significant in markdown
                        $sanitizedBlock['source'] = (string) $block['source'];
                    }
                    $sanitized['blocks'][] = $sanitizedBlock;
                }
            }
        }

        return $sanitized;
    }
}
```

### Key Features:
- **Input cleaning**: Trims whitespace and normalizes data
- **Type safety**: Ensures proper data types
- **Markdown preservation**: Doesn't trim source content where whitespace matters
- **Null handling**: Converts empty strings to null where appropriate

### Validation:
- [ ] Sanitizer service is created
- [ ] Implements SanitizerInterface
- [ ] Properly handles different field types
- [ ] Preserves significant whitespace in content

---

## Step 5.6: Create Content Validation Service
**Time:** 15 minutes

### Instructions:
1. Create `src/Validation/ContentValidationService.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation;

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Contracts\ContentValidatorInterface;
use PortableContent\Contracts\SanitizerInterface;
use PortableContent\Validation\ValueObjects\ValidationResult;
use PortableContent\Exception\ValidationException;
use PortableContent\Validation\ValueObjects\ContentCreationRequest;

final class ContentValidationService
{
    public function __construct(
        private readonly ContentValidatorInterface $validator,
        private readonly SanitizerInterface $sanitizer
    ) {}

    /**
     * Validate and sanitize content creation request
     */
    public function validateContentCreation(array $data): ContentCreationRequest
    {
        // First sanitize the input
        $sanitized = $this->sanitizer->sanitize($data);

        // Then validate the sanitized data
        $result = $this->validator->validateContentCreation($sanitized);

        if (!$result->isValid()) {
            throw new ValidationException($result->getErrors());
        }

        return ContentCreationRequest::fromArray($sanitized);
    }

    /**
     * Validate and sanitize content update request
     */
    public function validateContentUpdate(array $data): array
    {
        // First sanitize the input
        $sanitized = $this->sanitizer->sanitize($data);

        // Then validate the sanitized data
        $result = $this->validator->validateContentUpdate($sanitized);

        if (!$result->isValid()) {
            throw new ValidationException($result->getErrors());
        }

        return $sanitized;
    }

    /**
     * Validate that a ContentItem object is valid
     */
    public function validateContentItem(ContentItem $content): void
    {
        $contentData = [
            'type' => $content->type,
            'title' => $content->title,
            'summary' => $content->summary,
            'blocks' => array_map(fn($block) => [
                'kind' => 'markdown',
                'source' => $block->source
            ], $content->blocks)
        ];

        $result = $this->validator->validateContentCreation($contentData);

        if (!$result->isValid()) {
            throw new ValidationException($result->getErrors());
        }
    }

    /**
     * Create ContentItem from validated DTO
     */
    public function createContentFromDTO(ContentCreationRequest $dto): ContentItem
    {
        $blocks = [];
        foreach ($dto->blocks as $blockData) {
            $blocks[] = MarkdownBlock::create($blockData->source);
        }

        return ContentItem::create(
            type: $dto->type,
            title: $dto->title,
            summary: $dto->summary,
            blocks: $blocks
        );
    }

    /**
     * Get validation statistics for monitoring
     */
    public function getValidationStats(array $data): array
    {
        $stats = [
            'content_fields' => 0,
            'blocks_count' => 0,
            'total_content_length' => 0,
            'validation_errors' => 0
        ];

        if (isset($data['type'])) $stats['content_fields']++;
        if (isset($data['title'])) $stats['content_fields']++;
        if (isset($data['summary'])) $stats['content_fields']++;

        if (isset($data['blocks']) && is_array($data['blocks'])) {
            $stats['blocks_count'] = count($data['blocks']);
            foreach ($data['blocks'] as $block) {
                if (isset($block['source'])) {
                    $stats['total_content_length'] += strlen($block['source']);
                }
            }
        }

        $result = $this->validator->validateContentCreation($data);
        if (!$result->isValid()) {
            $stats['validation_errors'] = count($result->getAllMessages());
        }

        return $stats;
    }
}
```

### Key Features:
- **Dependency injection**: Users inject their preferred validator and sanitizer
- **Service orchestration**: Combines sanitization and validation
- **Value object integration**: Returns type-safe value objects instead of arrays
- **Exception handling**: Converts ValidationResult to exceptions
- **Statistics**: Monitoring and debugging support

### Validation:
- [ ] Service accepts validator via dependency injection
- [ ] Service combines sanitization and validation
- [ ] Returns type-safe value objects
- [ ] Proper exception handling
- [ ] Statistics for monitoring

---

## Step 5.7: Create ValidationException
**Time:** 10 minutes

### Instructions:
1. Create `src/Exception/ValidationException.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Exception;

use InvalidArgumentException;

final class ValidationException extends InvalidArgumentException
{
    /**
     * @param array<string, string[]> $errors Field name => array of error messages
     */
    public function __construct(
        private readonly array $errors,
        string $message = 'Validation failed'
    ) {
        parent::__construct($message);
    }

    /**
     * @return array<string, string[]>
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Get all error messages as a flat array
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
     * Get errors for a specific field
     */
    public function getFieldErrors(string $field): array
    {
        return $this->errors[$field] ?? [];
    }

    /**
     * Check if a specific field has errors
     */
    public function hasFieldErrors(string $field): bool
    {
        return !empty($this->errors[$field]);
    }
}
```

### Key Features:
- **Structured errors**: Field-specific error arrays
- **Multiple errors per field**: Can have multiple validation issues
- **Utility methods**: Easy access to errors in different formats
- **Exception folder**: Follows user's preferred folder structure

### Validation:
- [ ] Exception class is created in Exception/ folder
- [ ] Supports multiple errors per field
- [ ] Provides utility methods for error access
- [ ] Follows established project structure

---

## Step 5.8: Test the Validation System
**Time:** 25-30 minutes

### Instructions:
1. Create `test_validation.php`:

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Exception\ValidationException;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Validation\ContentValidationService;
use PortableContent\Validation\ContentSanitizer;
use Symfony\Component\Validator\Validation;

echo "Testing Validation System...\n\n";

// Create validation service with Symfony adapter
$symfonyValidator = Validation::createValidator();
$validator = new SymfonyValidatorAdapter($symfonyValidator);
$sanitizer = new ContentSanitizer();
$validationService = new ContentValidationService($validator, $sanitizer);

// Test 1: Valid content creation
echo "1. Testing valid content creation:\n";
$validData = [
    'type' => 'note',
    'title' => 'Test Note',
    'summary' => 'A test note for validation',
    'blocks' => [
        [
            'kind' => 'markdown',
            'source' => '# Hello World\n\nThis is a test note.'
        ]
    ]
];

try {
    $request = $validationService->validateContentCreation($validData);
    echo "   SUCCESS: Valid data passed validation\n";
    echo "   Request type: '{$request->type}'\n";
    echo "   Request title: '{$request->title}'\n";
    echo "   Request blocks count: " . count($request->blocks) . "\n\n";
} catch (ValidationException $e) {
    echo "   ERROR: " . implode(', ', $e->getAllMessages()) . "\n\n";
}

// Test 2: Invalid content - missing type
echo "2. Testing invalid content (missing type):\n";
$invalidData = [
    'title' => 'Test Note',
    'blocks' => [
        [
            'kind' => 'markdown',
            'source' => '# Hello World'
        ]
    ]
];

try {
    $validationService->validateContentCreation($invalidData);
    echo "   ERROR: Should have failed validation!\n\n";
} catch (ValidationException $e) {
    echo "   SUCCESS: Validation failed as expected\n";
    echo "   Errors: " . implode(', ', $e->getAllMessages()) . "\n\n";
}

// Test 3: Invalid content - empty blocks
echo "3. Testing invalid content (empty blocks):\n";
$invalidData = [
    'type' => 'note',
    'title' => 'Test Note',
    'blocks' => []
];

try {
    $validationService->validateContentCreation($invalidData);
    echo "   ERROR: Should have failed validation!\n\n";
} catch (ValidationException $e) {
    echo "   SUCCESS: Validation failed as expected\n";
    echo "   Errors: " . implode(', ', $e->getAllMessages()) . "\n\n";
}

// Test 4: Invalid block content
echo "4. Testing invalid block content:\n";
$invalidData = [
    'type' => 'note',
    'title' => 'Test Note',
    'blocks' => [
        [
            'kind' => 'markdown',
            'source' => ''  // Empty source
        ]
    ]
];

try {
    $validationService->validateContentCreation($invalidData);
    echo "   ERROR: Should have failed validation!\n\n";
} catch (ValidationException $e) {
    echo "   SUCCESS: Validation failed as expected\n";
    echo "   Errors: " . implode(', ', $e->getAllMessages()) . "\n\n";
}

// Test 5: Test sanitization
echo "5. Testing input sanitization:\n";
$messyData = [
    'type' => '  note  ',
    'title' => '  Test Note  ',
    'summary' => '  A test summary  ',
    'blocks' => [
        [
            'kind' => '  markdown  ',
            'source' => '# Hello World\n\nThis is a test.'
        ]
    ]
];

try {
    $request = $validationService->validateContentCreation($messyData);
    echo "   SUCCESS: Data sanitized\n";
    echo "   Original type: '{$messyData['type']}'\n";
    echo "   Sanitized type: '{$request->type}'\n";
    echo "   Original title: '{$messyData['title']}'\n";
    echo "   Sanitized title: '{$request->title}'\n\n";
} catch (ValidationException $e) {
    echo "   ERROR: " . implode(', ', $e->getAllMessages()) . "\n\n";
}

// Test 6: Test object validation
echo "6. Testing object validation:\n";
try {
    $block = MarkdownBlock::create('# Valid Block\n\nThis is valid.');
    $content = ContentItem::create('note', 'Valid Content')
        ->addBlock($block);

    $validationService->validateContentItem($content);
    echo "   SUCCESS: Valid ContentItem passed validation\n\n";
} catch (ValidationException $e) {
    echo "   ERROR: " . implode(', ', $e->getAllMessages()) . "\n\n";
}

// Test 7: Test content creation from value object
echo "7. Testing content creation from value object:\n";
try {
    $request = $validationService->validateContentCreation($validData);
    $content = $validationService->createContentFromDTO($request);

    echo "   SUCCESS: ContentItem created from value object\n";
    echo "   Content ID: {$content->id}\n";
    echo "   Content type: {$content->type}\n";
    echo "   Block count: " . count($content->blocks) . "\n\n";
} catch (ValidationException $e) {
    echo "   ERROR: " . implode(', ', $e->getAllMessages()) . "\n\n";
}

// Test 8: Test validation statistics
echo "8. Testing validation statistics:\n";
$stats = $validationService->getValidationStats($validData);
echo "   Content fields: {$stats['content_fields']}\n";
echo "   Blocks count: {$stats['blocks_count']}\n";
echo "   Total content length: {$stats['total_content_length']} characters\n";
echo "   Validation errors: {$stats['validation_errors']}\n\n";

// Test 9: Test field-specific errors
echo "9. Testing field-specific error handling:\n";
$multiErrorData = [
    'type' => '',  // Invalid
    'title' => str_repeat('x', 300),  // Too long
    'blocks' => [
        [
            'kind' => 'invalid',  // Wrong kind
            'source' => ''  // Empty
        ]
    ]
];

try {
    $validationService->validateContentCreation($multiErrorData);
    echo "   ERROR: Should have failed validation!\n\n";
} catch (ValidationException $e) {
    echo "   SUCCESS: Multiple validation errors caught\n";
    echo "   Type errors: " . implode(', ', $e->getFieldErrors('type')) . "\n";
    echo "   Title errors: " . implode(', ', $e->getFieldErrors('title')) . "\n";
    echo "   Block errors: " . implode(', ', $e->getFieldErrors('blocks')) . "\n\n";
}

// Test 10: Test validator adapter flexibility
echo "10. Testing validator adapter flexibility:\n";
try {
    // Users can create their own validator instances
    $customValidator = new SymfonyValidatorAdapter($symfonyValidator);
    $customService = new ContentValidationService($customValidator, $sanitizer);

    $testData = [
        'type' => 'note',
        'title' => 'Custom Validator Test',
        'blocks' => [
            [
                'kind' => 'markdown',
                'source' => '# Custom Test'
            ]
        ]
    ];

    $request = $customService->validateContentCreation($testData);
    echo "   SUCCESS: Custom validator adapter works\n";
    echo "   Validated type: {$request->type}\n";
} catch (Exception $e) {
    echo "   ERROR: Custom validator failed: {$e->getMessage()}\n";
}

echo "\nValidation tests completed!\n";
```

2. Run the test:
```bash
php test_validation.php
```

### Expected Output:
```
Testing Validation System...

1. Testing valid content creation:
   SUCCESS: Valid data passed validation
   Request type: 'note'
   Request title: 'Test Note'
   Request blocks count: 1

2. Testing invalid content (missing type):
   SUCCESS: Validation failed as expected
   Errors: type: Type is required

3. Testing invalid content (empty blocks):
   SUCCESS: Validation failed as expected
   Errors: blocks: At least one block is required

4. Testing invalid block content:
   SUCCESS: Validation failed as expected
   Errors: blocks: Block 0 source: Source is required

5. Testing input sanitization:
   SUCCESS: Data sanitized
   Original type: '  note  '
   Sanitized type: 'note'
   Original title: '  Test Note  '
   Sanitized title: 'Test Note'

6. Testing object validation:
   SUCCESS: Valid ContentItem passed validation

7. Testing content creation from value object:
   SUCCESS: ContentItem created from value object
   Content ID: 12345678-1234-1234-1234-123456789abc
   Content type: note
   Block count: 1

8. Testing validation statistics:
   Content fields: 3
   Blocks count: 1
   Total content length: 32 characters
   Validation errors: 0

9. Testing field-specific error handling:
   SUCCESS: Multiple validation errors caught
   Type errors: Type is required
   Title errors: Title must be 255 characters or less
   Block errors: Block 0 kind: Block kind must be 'markdown' (got 'invalid'), Block 0 source: Source is required

10. Testing validator adapter flexibility:
   SUCCESS: Custom validator adapter works
   Validated type: note

Validation tests completed!
```

### Validation:
- [ ] All validation tests pass
- [ ] DTOs are properly created and used
- [ ] Error messages are clear and specific
- [ ] Sanitization works correctly
- [ ] Field-specific errors are properly categorized
- [ ] Object validation works for existing objects
- [ ] Factory creates validators correctly
- [ ] Statistics provide useful information

---

## Step 5.9: Create Validation Unit Tests
**Time:** 25-30 minutes

### Instructions:
1. Create `tests/Unit/Validation/SymfonyValidatorAdapterTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\Tests\TestCase;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use Symfony\Component\Validator\Validation;

final class SymfonyValidatorAdapterTest extends TestCase
{
    private SymfonyValidatorAdapter $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $symfonyValidator = Validation::createValidator();
        $this->validator = new SymfonyValidatorAdapter($symfonyValidator);
    }

    public function testValidateContentCreationWithValidData(): void
    {
        $validData = [
            'type' => 'note',
            'title' => 'Test Note',
            'summary' => 'A test note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $result = $this->validator->validateContentCreation($validData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateContentCreationWithMissingType(): void
    {
        $invalidData = [
            'title' => 'Test Note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('type'));
        $this->assertContains('Type is required', $result->getFieldErrors('type'));
    }

    public function testValidateContentCreationWithInvalidTypeCharacters(): void
    {
        $invalidData = [
            'type' => 'note-with-dashes',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('type'));
        $this->assertContains('Type must contain only letters, numbers, and underscores', $result->getFieldErrors('type'));
    }

    public function testValidateContentCreationWithTooLongTitle(): void
    {
        $invalidData = [
            'type' => 'note',
            'title' => str_repeat('x', 256), // Too long
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('title'));
        $this->assertContains('Title must be 255 characters or less', $result->getFieldErrors('title'));
    }

    public function testValidateContentCreationWithEmptyBlocks(): void
    {
        $invalidData = [
            'type' => 'note',
            'title' => 'Test Note',
            'blocks' => []
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('blocks'));
        $this->assertContains('At least one block is required', $result->getFieldErrors('blocks'));
    }

    public function testValidateContentUpdateAllowsPartialData(): void
    {
        $partialData = [
            'title' => 'Updated Title'
        ];

        $result = $this->validator->validateContentUpdate($partialData);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateBlockWithValidData(): void
    {
        $validBlock = [
            'kind' => 'markdown',
            'source' => '# Hello World'
        ];

        $result = $this->validator->validateBlock($validBlock);

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
    }

    public function testValidateBlockWithInvalidKind(): void
    {
        $invalidBlock = [
            'kind' => 'invalid',
            'source' => '# Hello World'
        ];

        $result = $this->validator->validateBlock($invalidBlock);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('kind'));
        $this->assertContains("Block kind must be 'markdown' (got 'invalid')", $result->getFieldErrors('kind'));
    }

    public function testValidateBlockWithEmptySource(): void
    {
        $invalidBlock = [
            'kind' => 'markdown',
            'source' => ''
        ];

        $result = $this->validator->validateBlock($invalidBlock);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('source'));
        $this->assertContains('Source is required', $result->getFieldErrors('source'));
    }

    public function testValidateBlockWithScriptTag(): void
    {
        $invalidBlock = [
            'kind' => 'markdown',
            'source' => '# Hello <script>alert("xss")</script>'
        ];

        $result = $this->validator->validateBlock($invalidBlock);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('source'));
        $this->assertContains('Script tags are not allowed in markdown content', $result->getFieldErrors('source'));
    }

    public function testMultipleValidationErrors(): void
    {
        $invalidData = [
            'type' => '',
            'title' => str_repeat('x', 300),
            'blocks' => []
        ];

        $result = $this->validator->validateContentCreation($invalidData);

        $this->assertFalse($result->isValid());
        $this->assertTrue($result->hasFieldErrors('type'));
        $this->assertTrue($result->hasFieldErrors('title'));
        $this->assertTrue($result->hasFieldErrors('blocks'));

        $this->assertContains('Type is required', $result->getFieldErrors('type'));
        $this->assertContains('Title must be 255 characters or less', $result->getFieldErrors('title'));
        $this->assertContains('At least one block is required', $result->getFieldErrors('blocks'));
    }
}
```

2. Create `tests/Unit/Validation/ContentValidationServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Tests\TestCase;
use PortableContent\Exception\ValidationException;
use PortableContent\Validation\ContentValidationService;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Validation\ContentSanitizer;
use Symfony\Component\Validator\Validation;

final class ContentValidationServiceTest extends TestCase
{
    private ContentValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $symfonyValidator = Validation::createValidator();
        $validator = new SymfonyValidatorAdapter($symfonyValidator);
        $sanitizer = new ContentSanitizer();
        $this->service = new ContentValidationService($validator, $sanitizer);
    }

    public function testValidateContentCreation(): void
    {
        $validData = [
            'type' => 'note',
            'title' => 'Test Note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $request = $this->service->validateContentCreation($validData);

        $this->assertEquals('note', $request->type);
        $this->assertEquals('Test Note', $request->title);
        $this->assertCount(1, $request->blocks);
        $this->assertEquals('markdown', $request->blocks[0]->kind);
        $this->assertEquals('# Hello World', $request->blocks[0]->source);
    }

    public function testValidateContentCreationWithInvalidData(): void
    {
        $invalidData = [
            'type' => '',
            'blocks' => []
        ];

        $this->expectException(ValidationException::class);
        $this->service->validateContentCreation($invalidData);
    }

    public function testCreateContentFromDTO(): void
    {
        $validData = [
            'type' => 'note',
            'title' => 'Test Note',
            'summary' => 'A test',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $request = $this->service->validateContentCreation($validData);
        $content = $this->service->createContentFromDTO($request);

        $this->assertEquals('note', $content->type);
        $this->assertEquals('Test Note', $content->title);
        $this->assertEquals('A test', $content->summary);
        $this->assertCount(1, $content->blocks);
        $this->assertEquals('# Hello World', $content->blocks[0]->source);
    }

    public function testValidateContentItem(): void
    {
        $block = MarkdownBlock::create('# Valid Block');
        $content = ContentItem::create('note', 'Valid Content')
            ->addBlock($block);

        $this->service->validateContentItem($content);
        $this->assertTrue(true); // Should not throw
    }

    public function testValidateContentItemWithInvalidContent(): void
    {
        $content = ContentItem::create('note', 'Valid Content');
        // No blocks - should be invalid

        $this->expectException(ValidationException::class);
        $this->service->validateContentItem($content);
    }

    public function testValidateContentUpdate(): void
    {
        $updateData = [
            'title' => 'Updated Title'
        ];

        $sanitized = $this->service->validateContentUpdate($updateData);

        $this->assertEquals('Updated Title', $sanitized['title']);
    }

    public function testGetValidationStats(): void
    {
        $data = [
            'type' => 'note',
            'title' => 'Test Note',
            'summary' => 'A test summary',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World\n\nThis is a test.'
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Section 2\n\nMore content.'
                ]
            ]
        ];

        $stats = $this->service->getValidationStats($data);

        $this->assertEquals(3, $stats['content_fields']); // type, title, summary
        $this->assertEquals(2, $stats['blocks_count']);
        $this->assertGreaterThan(0, $stats['total_content_length']);
        $this->assertEquals(0, $stats['validation_errors']); // Valid data
    }

    public function testGetValidationStatsWithInvalidData(): void
    {
        $invalidData = [
            'type' => '',
            'blocks' => []
        ];

        $stats = $this->service->getValidationStats($invalidData);

        $this->assertGreaterThan(0, $stats['validation_errors']);
    }
}
```

3. Run the tests:
```bash
vendor/bin/phpunit tests/Unit/Validation/
```

4. Run the validation tests:
```bash
./vendor/bin/phpunit --testsuite=Unit tests/Unit/Validation/
```

### Validation:
- [ ] Validation unit tests are created
- [ ] All validation scenarios are tested
- [ ] Error handling is thoroughly tested
- [ ] Service methods are tested
- [ ] Factory pattern is tested
- [ ] All tests pass successfully

---

## Step 5.10: Clean Up and Document
**Time:** 15 minutes

### Instructions:
1. Delete the test file:
```bash
rm test_validation.php
```

2. Update README.md to add validation usage section:

Add this after the "Repository Usage" section:

```markdown
## Input Validation

### Basic Usage with Factory

```php
<?php

use PortableContent\Exception\ValidationException;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Validation\ContentValidationService;
use PortableContent\Validation\ContentSanitizer;
use Symfony\Component\Validator\Validation;

// Create validation service with Symfony validator
$symfonyValidator = Validation::createValidator();
$validator = new SymfonyValidatorAdapter($symfonyValidator);
$sanitizer = new ContentSanitizer();
$validationService = new ContentValidationService($validator, $sanitizer);

// Raw input data (e.g., from API request)
$inputData = [
    'type' => 'note',
    'title' => 'My Note',
    'summary' => 'A simple note',
    'blocks' => [
        [
            'kind' => 'markdown',
            'source' => '# Hello World\n\nThis is my note content.'
        ]
    ]
];

try {
    // Validate and get type-safe value object
    $request = $validationService->validateContentCreation($inputData);

    // Create content from value object
    $content = $validationService->createContentFromDTO($request);

    // Save to repository
    $repository->save($content);

} catch (ValidationException $e) {
    // Handle validation errors
    foreach ($e->getErrors() as $field => $errors) {
        echo "{$field}: " . implode(', ', $errors) . "\n";
    }
}
```

### Available Validators

- **Native**: No external dependencies, built-in validation
- **Future**: Respect/Validation, Symfony Validator, Laravel Validator adapters

### Validation Features

- **Adapter Pattern**: Pluggable validation libraries
- **Type Safety**: Value objects instead of primitive arrays
- **Comprehensive validation**: Type, length, format, and content validation
- **Input sanitization**: Automatic trimming and normalization
- **Field-specific errors**: Clear error messages for each field
- **Security checks**: Basic protection against malicious content
- **Object validation**: Can validate existing ContentItem objects
- **Statistics**: Monitoring and debugging support
```

### Validation:
- [ ] Test file is cleaned up
- [ ] README.md includes validation usage with factory pattern
- [ ] Examples show proper error handling and DTOs
- [ ] Documentation covers adapter pattern and features

---

## Step 5.11: Commit the Changes
**Time:** 5 minutes

### Instructions:
1. Stage all changes:
```bash
git add .
```

2. Commit with descriptive message:
```bash
git commit -m "Implement adapter-based validation system with DTOs

- Created ContentValidatorInterface and ValidationResult contracts
- Implemented SymfonyValidatorAdapter using proven Symfony Validator
- Added ContentSanitizer for input cleaning and normalization
- Implemented ContentValidationService with dependency injection
- Added type-safe value objects (ContentCreationRequest, BlockData, ValidationResult)
- Created ValidationException in Exception/ folder
- Added comprehensive unit tests for all components
- Updated README with dependency injection usage examples

Validation system uses dependency injection for maximum flexibility while maintaining
clean interfaces and type safety."
```

3. Push to GitHub:
```bash
git push origin main
```

### Validation:
- [ ] All files are committed
- [ ] Commit message describes the adapter-based system
- [ ] Changes are pushed to GitHub
- [ ] Validation layer is complete

---

## Completion Checklist

### Core Architecture:
- [ ] ContentValidatorInterface and ValidationResult contracts in Contracts/
- [ ] ValidationException in Exception/ folder following project structure
- [ ] Adapter pattern supporting multiple validation libraries
- [ ] Dependency injection for maximum flexibility (no factory lock-in)

### Validation Components:
- [ ] SymfonyValidatorAdapter using proven Symfony Validator library
- [ ] ContentSanitizer for input cleaning and normalization
- [ ] ContentValidationService as high-level orchestration layer
- [ ] Dependency injection allows users to bring their own adapters

### Value Objects:
- [ ] ContentCreationRequest value object for type-safe validated data
- [ ] BlockData value object for structured block information
- [ ] ValidationResult value object for validation outcomes
- [ ] Factory methods for array conversion
- [ ] Immutable design preventing data corruption

### Validation Features:
- [ ] Field-specific error reporting with clear messages
- [ ] Security checks for malicious content (script tags, encoding)
- [ ] Object validation for existing ContentItem instances
- [ ] Statistics for monitoring and debugging
- [ ] Separate creation and update validation logic

### Testing and Documentation:
- [ ] Comprehensive unit tests for all components
- [ ] Adapter pattern tested with dependency injection
- [ ] Error handling thoroughly verified
- [ ] README updated with dependency injection usage examples

---

## Next Steps

With Task 5 complete, you now have a flexible, adapter-based validation system that:

- **Supports multiple validation libraries** through the adapter pattern
- **Provides type safety** with DTOs instead of primitive arrays
- **Ensures data integrity** with comprehensive validation rules
- **Offers clear error reporting** with field-specific messages
- **Maintains clean architecture** with proper separation of concerns

The validation layer integrates seamlessly with your data classes and repository pattern while giving users the flexibility to choose their preferred validation library.

You're ready to move on to **Task 6: Testing Setup**, where you'll implement a comprehensive test suite using PHPUnit to ensure all components work correctly together.

## Future Enhancements

### Additional Validator Adapters:
- **Respect/Validation Adapter**: For fluent, composable validation
- **Symfony Validator Adapter**: For enterprise-grade validation with attributes
- **Laravel Validator Adapter**: For Laravel-style validation rules

### Advanced Features:
- **Validation Groups**: Different validation rules for different contexts
- **Conditional Validation**: Rules that depend on other field values
- **Custom Validators**: Easy extension for domain-specific validation
- **Async Validation**: For validation that requires external services

## Troubleshooting

### Common Issues:

**Adding new validation libraries:**
- Implement ContentValidatorInterface in a new adapter
- Users inject the adapter directly into ContentValidationService
- No need to modify any factory or central registry

**Custom validation rules:**
- Create a custom adapter implementing ContentValidatorInterface
- Or extend SymfonyValidatorAdapter and add custom constraints
- Users inject their custom adapter into the service

**Performance with large content:**
- Consider streaming validation for very large content
- Add validation result caching if needed
- Profile validation performance and optimize bottlenecks

**Integration with frameworks:**
- Create framework-specific service providers
- Add configuration files for validation rules
- Integrate with framework validation systems
