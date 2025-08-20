# Task 5: Input Validation - Detailed Steps

## Overview
Implement comprehensive input validation for content creation and updates. This creates a robust validation layer that ensures data integrity and provides clear error messages for invalid input.

**Estimated Time:** 1-2 hours  
**Dependencies:** Task 4 (Repository Pattern) must be completed

---

## Step 5.1: Design the Validation Strategy
**Time:** 10-15 minutes

### Instructions:
Before implementing, let's define what needs validation and how:

**Content-Level Validation:**
- `type`: Required, non-empty string, max 50 characters
- `title`: Optional, max 255 characters if provided
- `summary`: Optional, max 1000 characters if provided
- `blocks`: Required, at least one block, all must be valid

**Block-Level Validation:**
- `source`: Required, non-empty after trimming, max 100KB
- `kind`: Must be 'markdown' (for Phase 1A)

**Validation Approach:**
- **Early validation**: Validate before creating objects
- **Clear error messages**: Specific field-level errors
- **Structured errors**: Array of validation errors with field names
- **Sanitization**: Trim whitespace, normalize input

### Validation Rules Summary:
```php
ContentItem:
  - type: required, 1-50 chars, alphanumeric + underscore
  - title: optional, max 255 chars
  - summary: optional, max 1000 chars
  - blocks: required, 1-10 blocks max

MarkdownBlock:
  - source: required, 1-100000 chars
  - kind: must be 'markdown'
```

### Validation:
- [ ] Validation rules are clearly defined
- [ ] Limits are reasonable for the use case
- [ ] Error message strategy is planned
- [ ] Sanitization approach is decided

---

## Step 5.2: Create Validation Exception Classes
**Time:** 10 minutes

### Instructions:
1. Create `src/Validation/ValidationException.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation;

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

    /**
     * Create from a simple array of field => message pairs
     */
    public static function fromSimpleErrors(array $errors): self
    {
        $structured = [];
        foreach ($errors as $field => $message) {
            $structured[$field] = [$message];
        }
        return new self($structured);
    }
}
```

### Key Features:
- **Structured errors**: Field-specific error arrays
- **Multiple errors per field**: Can have multiple validation issues
- **Utility methods**: Easy access to errors in different formats
- **Factory method**: Simple creation from basic error arrays

### Validation:
- [ ] Exception class is created
- [ ] Supports multiple errors per field
- [ ] Provides utility methods for error access
- [ ] Has factory method for simple cases

---

## Step 5.3: Create Content Validator Class
**Time:** 20-25 minutes

### Instructions:
1. Create `src/Validation/ContentValidator.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation;

final class ContentValidator
{
    private const MAX_TYPE_LENGTH = 50;
    private const MAX_TITLE_LENGTH = 255;
    private const MAX_SUMMARY_LENGTH = 1000;
    private const MAX_BLOCKS = 10;
    private const MIN_BLOCKS = 1;

    /**
     * Validate content creation data
     * 
     * @param array $data Raw input data
     * @throws ValidationException if validation fails
     */
    public function validateCreateRequest(array $data): void
    {
        $errors = [];

        // Validate type
        $typeErrors = $this->validateType($data['type'] ?? null);
        if (!empty($typeErrors)) {
            $errors['type'] = $typeErrors;
        }

        // Validate title (optional)
        if (isset($data['title'])) {
            $titleErrors = $this->validateTitle($data['title']);
            if (!empty($titleErrors)) {
                $errors['title'] = $titleErrors;
            }
        }

        // Validate summary (optional)
        if (isset($data['summary'])) {
            $summaryErrors = $this->validateSummary($data['summary']);
            if (!empty($summaryErrors)) {
                $errors['summary'] = $summaryErrors;
            }
        }

        // Validate blocks
        $blocksErrors = $this->validateBlocks($data['blocks'] ?? []);
        if (!empty($blocksErrors)) {
            $errors['blocks'] = $blocksErrors;
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Validate content update data (similar to create but allows partial updates)
     */
    public function validateUpdateRequest(array $data): void
    {
        $errors = [];

        // Type is optional for updates, but if provided must be valid
        if (isset($data['type'])) {
            $typeErrors = $this->validateType($data['type']);
            if (!empty($typeErrors)) {
                $errors['type'] = $typeErrors;
            }
        }

        // Title validation (optional)
        if (isset($data['title'])) {
            $titleErrors = $this->validateTitle($data['title']);
            if (!empty($titleErrors)) {
                $errors['title'] = $titleErrors;
            }
        }

        // Summary validation (optional)
        if (isset($data['summary'])) {
            $summaryErrors = $this->validateSummary($data['summary']);
            if (!empty($summaryErrors)) {
                $errors['summary'] = $summaryErrors;
            }
        }

        // Blocks validation (optional for updates)
        if (isset($data['blocks'])) {
            $blocksErrors = $this->validateBlocks($data['blocks']);
            if (!empty($blocksErrors)) {
                $errors['blocks'] = $blocksErrors;
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Sanitize input data by trimming strings and normalizing
     */
    public function sanitizeInput(array $data): array
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
                        $sanitizedBlock['source'] = (string) $block['source'];
                    }
                    $sanitized['blocks'][] = $sanitizedBlock;
                }
            }
        }

        return $sanitized;
    }

    private function validateType(?string $type): array
    {
        $errors = [];

        if ($type === null || $type === '') {
            $errors[] = 'Type is required';
            return $errors;
        }

        if (strlen($type) > self::MAX_TYPE_LENGTH) {
            $errors[] = "Type must be {self::MAX_TYPE_LENGTH} characters or less";
        }

        if (!preg_match('/^[a-zA-Z0-9_]+$/', $type)) {
            $errors[] = 'Type must contain only letters, numbers, and underscores';
        }

        return $errors;
    }

    private function validateTitle(?string $title): array
    {
        $errors = [];

        if ($title !== null && strlen($title) > self::MAX_TITLE_LENGTH) {
            $errors[] = "Title must be {self::MAX_TITLE_LENGTH} characters or less";
        }

        return $errors;
    }

    private function validateSummary(?string $summary): array
    {
        $errors = [];

        if ($summary !== null && strlen($summary) > self::MAX_SUMMARY_LENGTH) {
            $errors[] = "Summary must be {self::MAX_SUMMARY_LENGTH} characters or less";
        }

        return $errors;
    }

    private function validateBlocks(array $blocks): array
    {
        $errors = [];

        if (empty($blocks)) {
            $errors[] = 'At least one block is required';
            return $errors;
        }

        if (count($blocks) > self::MAX_BLOCKS) {
            $errors[] = "Maximum {self::MAX_BLOCKS} blocks allowed";
        }

        foreach ($blocks as $index => $block) {
            $blockErrors = $this->validateBlock($block, $index);
            if (!empty($blockErrors)) {
                $errors = array_merge($errors, $blockErrors);
            }
        }

        return $errors;
    }

    private function validateBlock(mixed $block, int $index): array
    {
        $errors = [];

        if (!is_array($block)) {
            $errors[] = "Block {$index} must be an array";
            return $errors;
        }

        // Validate kind
        $kind = $block['kind'] ?? null;
        if ($kind === null || $kind === '') {
            $errors[] = "Block {$index} must have a 'kind' field";
        } elseif ($kind !== 'markdown') {
            $errors[] = "Block {$index} kind must be 'markdown' (got '{$kind}')";
        }

        // Validate source
        $source = $block['source'] ?? null;
        if ($source === null || $source === '') {
            $errors[] = "Block {$index} must have a 'source' field";
        } elseif (is_string($source)) {
            if (trim($source) === '') {
                $errors[] = "Block {$index} source cannot be empty";
            } elseif (strlen($source) > 100000) {
                $errors[] = "Block {$index} source must be 100KB or less";
            }
        } else {
            $errors[] = "Block {$index} source must be a string";
        }

        return $errors;
    }
}
```

### Key Features:
- **Comprehensive validation**: All fields with appropriate rules
- **Structured error reporting**: Field-specific errors with context
- **Sanitization**: Input cleaning and normalization
- **Flexible validation**: Separate methods for create vs update
- **Clear error messages**: Specific, actionable error descriptions

### Validation:
- [ ] Validator class is created
- [ ] All validation rules are implemented
- [ ] Error messages are clear and specific
- [ ] Sanitization handles edge cases
- [ ] Both create and update validation supported

---

## Step 5.4: Create Block Validator Class
**Time:** 15-20 minutes

### Instructions:
1. Create `src/Validation/BlockValidator.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation;

final class BlockValidator
{
    private const MAX_SOURCE_LENGTH = 100000; // 100KB
    private const MIN_SOURCE_LENGTH = 1;

    /**
     * Validate markdown block data
     */
    public function validateMarkdownBlock(array $data): void
    {
        $errors = [];

        // Validate kind
        $kind = $data['kind'] ?? null;
        if ($kind !== 'markdown') {
            $errors['kind'] = ['Block kind must be "markdown"'];
        }

        // Validate source
        $sourceErrors = $this->validateSource($data['source'] ?? null);
        if (!empty($sourceErrors)) {
            $errors['source'] = $sourceErrors;
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Validate just the source content
     */
    public function validateSource(?string $source): array
    {
        $errors = [];

        if ($source === null) {
            $errors[] = 'Source is required';
            return $errors;
        }

        if (!is_string($source)) {
            $errors[] = 'Source must be a string';
            return $errors;
        }

        $trimmedSource = trim($source);
        if (strlen($trimmedSource) < self::MIN_SOURCE_LENGTH) {
            $errors[] = 'Source cannot be empty';
        }

        if (strlen($source) > self::MAX_SOURCE_LENGTH) {
            $errors[] = 'Source must be ' . self::MAX_SOURCE_LENGTH . ' characters or less';
        }

        // Basic markdown validation (optional)
        $markdownErrors = $this->validateMarkdownSyntax($source);
        if (!empty($markdownErrors)) {
            $errors = array_merge($errors, $markdownErrors);
        }

        return $errors;
    }

    /**
     * Sanitize block data
     */
    public function sanitizeBlockData(array $data): array
    {
        $sanitized = [];

        if (isset($data['kind'])) {
            $sanitized['kind'] = trim((string) $data['kind']);
        }

        if (isset($data['source'])) {
            // Don't trim source content as whitespace might be significant in markdown
            $sanitized['source'] = (string) $data['source'];
        }

        return $sanitized;
    }

    /**
     * Basic markdown syntax validation
     */
    private function validateMarkdownSyntax(string $source): array
    {
        $errors = [];

        // Check for potentially dangerous content (basic security)
        if (preg_match('/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/mi', $source)) {
            $errors[] = 'Script tags are not allowed in markdown content';
        }

        // Check for extremely long lines (might indicate malformed content)
        $lines = explode("\n", $source);
        foreach ($lines as $lineNum => $line) {
            if (strlen($line) > 10000) {
                $errors[] = "Line " . ($lineNum + 1) . " is too long (max 10,000 characters per line)";
                break; // Only report first occurrence
            }
        }

        return $errors;
    }

    /**
     * Validate that source contains valid UTF-8
     */
    public function validateEncoding(string $source): array
    {
        $errors = [];

        if (!mb_check_encoding($source, 'UTF-8')) {
            $errors[] = 'Source must be valid UTF-8 encoded text';
        }

        return $errors;
    }

    /**
     * Get source statistics for validation context
     */
    public function getSourceStats(string $source): array
    {
        return [
            'length' => strlen($source),
            'lines' => substr_count($source, "\n") + 1,
            'words' => str_word_count($source),
            'encoding' => mb_detect_encoding($source, ['UTF-8', 'ASCII'], true)
        ];
    }
}
```

### Key Features:
- **Markdown-specific validation**: Tailored for markdown content
- **Security checks**: Basic protection against script injection
- **Encoding validation**: Ensures valid UTF-8 content
- **Statistics**: Useful for debugging and monitoring
- **Flexible validation**: Can be extended for other block types

### Validation:
- [ ] Block validator is created
- [ ] Markdown-specific rules are implemented
- [ ] Security checks are in place
- [ ] Encoding validation works
- [ ] Statistics method provides useful info

---

## Step 5.5: Create Validation Service
**Time:** 15-20 minutes

### Instructions:
1. Create `src/Validation/ValidationService.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Validation;

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;

final class ValidationService
{
    public function __construct(
        private readonly ContentValidator $contentValidator = new ContentValidator(),
        private readonly BlockValidator $blockValidator = new BlockValidator()
    ) {}

    /**
     * Validate and sanitize content creation request
     */
    public function validateContentCreation(array $data): array
    {
        // First sanitize the input
        $sanitized = $this->contentValidator->sanitizeInput($data);

        // Then validate the sanitized data
        $this->contentValidator->validateCreateRequest($sanitized);

        return $sanitized;
    }

    /**
     * Validate and sanitize content update request
     */
    public function validateContentUpdate(array $data): array
    {
        // First sanitize the input
        $sanitized = $this->contentValidator->sanitizeInput($data);

        // Then validate the sanitized data
        $this->contentValidator->validateUpdateRequest($sanitized);

        return $sanitized;
    }

    /**
     * Validate that a ContentItem object is valid
     */
    public function validateContentItem(ContentItem $content): void
    {
        $errors = [];

        // Validate content-level properties
        $contentData = [
            'type' => $content->type,
            'title' => $content->title,
            'summary' => $content->summary,
        ];

        try {
            $this->contentValidator->validateCreateRequest($contentData + ['blocks' => ['dummy']]);
        } catch (ValidationException $e) {
            $contentErrors = $e->getErrors();
            unset($contentErrors['blocks']); // Remove dummy blocks error
            $errors = array_merge($errors, $contentErrors);
        }

        // Validate blocks
        if (empty($content->blocks)) {
            $errors['blocks'] = ['At least one block is required'];
        } else {
            foreach ($content->blocks as $index => $block) {
                try {
                    $this->validateMarkdownBlock($block);
                } catch (ValidationException $e) {
                    foreach ($e->getErrors() as $field => $fieldErrors) {
                        $errors["blocks.{$index}.{$field}"] = $fieldErrors;
                    }
                }
            }
        }

        if (!empty($errors)) {
            throw new ValidationException($errors);
        }
    }

    /**
     * Validate a MarkdownBlock object
     */
    public function validateMarkdownBlock(MarkdownBlock $block): void
    {
        $blockData = [
            'kind' => 'markdown',
            'source' => $block->source
        ];

        $this->blockValidator->validateMarkdownBlock($blockData);
    }

    /**
     * Create ContentItem from validated data
     */
    public function createContentFromValidatedData(array $validatedData): ContentItem
    {
        $blocks = [];
        foreach ($validatedData['blocks'] as $blockData) {
            $blocks[] = MarkdownBlock::create($blockData['source']);
        }

        return ContentItem::create(
            type: $validatedData['type'],
            title: $validatedData['title'] ?? null,
            summary: $validatedData['summary'] ?? null,
            blocks: $blocks
        );
    }

    /**
     * Update ContentItem with validated data
     */
    public function updateContentWithValidatedData(ContentItem $content, array $validatedData): ContentItem
    {
        $updated = $content;

        if (isset($validatedData['type'])) {
            // Note: ContentItem is immutable, so we'd need to create a new one
            // For now, type updates aren't supported in the immutable design
        }

        if (isset($validatedData['title'])) {
            $updated = $updated->withTitle($validatedData['title']);
        }

        if (isset($validatedData['blocks'])) {
            $blocks = [];
            foreach ($validatedData['blocks'] as $blockData) {
                $blocks[] = MarkdownBlock::create($blockData['source']);
            }
            $updated = $updated->withBlocks($blocks);
        }

        return $updated;
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

        try {
            $this->validateContentCreation($data);
        } catch (ValidationException $e) {
            $stats['validation_errors'] = count($e->getAllMessages());
        }

        return $stats;
    }
}
```

### Key Features:
- **High-level validation**: Combines content and block validation
- **Object validation**: Can validate existing ContentItem objects
- **Factory methods**: Create objects from validated data
- **Update helpers**: Update existing objects with validated data
- **Statistics**: Monitoring and debugging support

### Validation:
- [ ] Validation service is created
- [ ] Combines content and block validation
- [ ] Provides factory methods for object creation
- [ ] Supports both creation and update workflows
- [ ] Includes monitoring capabilities

---

## Step 5.6: Test the Validation System
**Time:** 20-25 minutes

### Instructions:
1. Create `test_validation.php`:

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Validation\ValidationService;
use PortableContent\Validation\ValidationException;

echo "Testing Validation System...\n\n";

$validationService = new ValidationService();

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
    $sanitized = $validationService->validateContentCreation($validData);
    echo "   SUCCESS: Valid data passed validation\n";
    echo "   Sanitized type: '{$sanitized['type']}'\n";
    echo "   Sanitized title: '{$sanitized['title']}'\n\n";
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
    $sanitized = $validationService->validateContentCreation($messyData);
    echo "   SUCCESS: Data sanitized\n";
    echo "   Original type: '{$messyData['type']}'\n";
    echo "   Sanitized type: '{$sanitized['type']}'\n";
    echo "   Original title: '{$messyData['title']}'\n";
    echo "   Sanitized title: '{$sanitized['title']}'\n\n";
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

// Test 7: Test content creation from validated data
echo "7. Testing content creation from validated data:\n";
try {
    $validatedData = $validationService->validateContentCreation($validData);
    $content = $validationService->createContentFromValidatedData($validatedData);
    
    echo "   SUCCESS: ContentItem created from validated data\n";
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

echo "Validation tests completed!\n";
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
   Sanitized type: 'note'
   Sanitized title: 'Test Note'

2. Testing invalid content (missing type):
   SUCCESS: Validation failed as expected
   Errors: type: Type is required

3. Testing invalid content (empty blocks):
   SUCCESS: Validation failed as expected
   Errors: blocks: At least one block is required

4. Testing invalid block content:
   SUCCESS: Validation failed as expected
   Errors: blocks: Block 0 source cannot be empty

5. Testing input sanitization:
   SUCCESS: Data sanitized
   Original type: '  note  '
   Sanitized type: 'note'
   Original title: '  Test Note  '
   Sanitized title: 'Test Note'

6. Testing object validation:
   SUCCESS: Valid ContentItem passed validation

7. Testing content creation from validated data:
   SUCCESS: ContentItem created from validated data
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
   Block errors: Block 0 kind must be 'markdown' (got 'invalid'), Block 0 source cannot be empty

Validation tests completed!
```

### Validation:
- [ ] All validation tests pass
- [ ] Error messages are clear and specific
- [ ] Sanitization works correctly
- [ ] Field-specific errors are properly categorized
- [ ] Object validation works for existing objects
- [ ] Statistics provide useful information

---

## Step 5.7: Create Validation Unit Tests
**Time:** 20-25 minutes

### Instructions:
1. Create `tests/Unit/Validation/ContentValidatorTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\Tests\TestCase;
use PortableContent\Validation\ContentValidator;
use PortableContent\Validation\ValidationException;

final class ContentValidatorTest extends TestCase
{
    private ContentValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ContentValidator();
    }

    public function testValidateCreateRequestWithValidData(): void
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

        $this->validator->validateCreateRequest($validData);
        $this->assertTrue(true); // Should not throw
    }

    public function testValidateCreateRequestWithMissingType(): void
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

        $this->expectException(ValidationException::class);
        $this->validator->validateCreateRequest($invalidData);
    }

    public function testValidateCreateRequestWithEmptyType(): void
    {
        $invalidData = [
            'type' => '',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateCreateRequest($invalidData);
    }

    public function testValidateCreateRequestWithInvalidTypeCharacters(): void
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

        $this->expectException(ValidationException::class);
        $this->validator->validateCreateRequest($invalidData);
    }

    public function testValidateCreateRequestWithTooLongTitle(): void
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

        $this->expectException(ValidationException::class);
        $this->validator->validateCreateRequest($invalidData);
    }

    public function testValidateCreateRequestWithEmptyBlocks(): void
    {
        $invalidData = [
            'type' => 'note',
            'title' => 'Test Note',
            'blocks' => []
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateCreateRequest($invalidData);
    }

    public function testValidateCreateRequestWithInvalidBlockKind(): void
    {
        $invalidData = [
            'type' => 'note',
            'blocks' => [
                [
                    'kind' => 'invalid',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateCreateRequest($invalidData);
    }

    public function testValidateCreateRequestWithEmptyBlockSource(): void
    {
        $invalidData = [
            'type' => 'note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => ''
                ]
            ]
        ];

        $this->expectException(ValidationException::class);
        $this->validator->validateCreateRequest($invalidData);
    }

    public function testSanitizeInput(): void
    {
        $messyData = [
            'type' => '  note  ',
            'title' => '  Test Title  ',
            'summary' => '  Test Summary  ',
            'blocks' => [
                [
                    'kind' => '  markdown  ',
                    'source' => '# Hello World'
                ]
            ]
        ];

        $sanitized = $this->validator->sanitizeInput($messyData);

        $this->assertEquals('note', $sanitized['type']);
        $this->assertEquals('Test Title', $sanitized['title']);
        $this->assertEquals('Test Summary', $sanitized['summary']);
        $this->assertEquals('markdown', $sanitized['blocks'][0]['kind']);
    }

    public function testSanitizeInputWithEmptyStrings(): void
    {
        $data = [
            'type' => 'note',
            'title' => '   ',
            'summary' => '   ',
            'blocks' => []
        ];

        $sanitized = $this->validator->sanitizeInput($data);

        $this->assertEquals('note', $sanitized['type']);
        $this->assertNull($sanitized['title']);
        $this->assertNull($sanitized['summary']);
    }

    public function testValidateUpdateRequestAllowsPartialData(): void
    {
        $partialData = [
            'title' => 'Updated Title'
        ];

        $this->validator->validateUpdateRequest($partialData);
        $this->assertTrue(true); // Should not throw
    }

    public function testValidationExceptionContainsFieldSpecificErrors(): void
    {
        $invalidData = [
            'type' => '',
            'title' => str_repeat('x', 300),
            'blocks' => []
        ];

        try {
            $this->validator->validateCreateRequest($invalidData);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertTrue($e->hasFieldErrors('type'));
            $this->assertTrue($e->hasFieldErrors('title'));
            $this->assertTrue($e->hasFieldErrors('blocks'));

            $this->assertContains('Type is required', $e->getFieldErrors('type'));
            $this->assertContains('Title must be 255 characters or less', $e->getFieldErrors('title'));
            $this->assertContains('At least one block is required', $e->getFieldErrors('blocks'));
        }
    }
}
```

2. Create `tests/Unit/Validation/ValidationServiceTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\ContentItem;
use PortableContent\MarkdownBlock;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\ValidationException;
use PortableContent\Validation\ValidationService;

final class ValidationServiceTest extends TestCase
{
    private ValidationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ValidationService();
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

        $sanitized = $this->service->validateContentCreation($validData);

        $this->assertEquals('note', $sanitized['type']);
        $this->assertEquals('Test Note', $sanitized['title']);
        $this->assertCount(1, $sanitized['blocks']);
    }

    public function testCreateContentFromValidatedData(): void
    {
        $validatedData = [
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

        $content = $this->service->createContentFromValidatedData($validatedData);

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

    public function testUpdateContentWithValidatedData(): void
    {
        $original = ContentItem::create('note', 'Original Title');

        $updateData = [
            'title' => 'Updated Title',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Updated Content'
                ]
            ]
        ];

        $updated = $this->service->updateContentWithValidatedData($original, $updateData);

        $this->assertEquals('Original Title', $original->title); // Original unchanged
        $this->assertEquals('Updated Title', $updated->title);
        $this->assertCount(1, $updated->blocks);
        $this->assertEquals('# Updated Content', $updated->blocks[0]->source);
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

3. Run the validation tests:
```bash
./vendor/bin/phpunit --testsuite=Unit tests/Unit/Validation/
```

### Validation:
- [ ] Validation unit tests are created
- [ ] All validation scenarios are tested
- [ ] Error handling is thoroughly tested
- [ ] Service methods are tested
- [ ] All tests pass successfully

---

## Step 5.8: Clean Up and Document
**Time:** 10 minutes

### Instructions:
1. Delete the test file:
```bash
rm test_validation.php
```

2. Update README.md to add validation usage section:

Add this after the "Repository Usage" section:

```markdown
## Input Validation

### Validating Content Creation

```php
<?php

use PortableContent\Validation\ValidationService;
use PortableContent\Validation\ValidationException;

$validationService = new ValidationService();

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
    // Validate and sanitize input
    $validatedData = $validationService->validateContentCreation($inputData);
    
    // Create content from validated data
    $content = $validationService->createContentFromValidatedData($validatedData);
    
    // Save to repository
    $repository->save($content);
    
} catch (ValidationException $e) {
    // Handle validation errors
    foreach ($e->getErrors() as $field => $errors) {
        echo "{$field}: " . implode(', ', $errors) . "\n";
    }
}
```

### Validation Features

- **Comprehensive validation**: Type, length, format, and content validation
- **Input sanitization**: Automatic trimming and normalization
- **Field-specific errors**: Clear error messages for each field
- **Security checks**: Basic protection against malicious content
- **Object validation**: Can validate existing ContentItem objects
- **Statistics**: Monitoring and debugging support
```

### Validation:
- [ ] Test file is cleaned up
- [ ] README.md includes validation usage
- [ ] Examples show proper error handling
- [ ] Documentation covers key features

---

## Step 5.9: Commit the Changes
**Time:** 5 minutes

### Instructions:
1. Stage all changes:
```bash
git add .
```

2. Commit with descriptive message:
```bash
git commit -m "Implement comprehensive input validation system

- Created ValidationException with structured error handling
- Implemented ContentValidator with field-specific validation rules
- Added BlockValidator with markdown-specific validation
- Created ValidationService as high-level validation facade
- Added input sanitization and security checks
- Implemented object validation for existing ContentItem instances
- Added validation statistics for monitoring
- Updated README with validation usage examples

Validation layer provides robust input validation and clear error reporting."
```

3. Push to GitHub:
```bash
git push origin main
```

### Validation:
- [ ] All files are committed
- [ ] Commit message describes the validation system
- [ ] Changes are pushed to GitHub
- [ ] Validation layer is complete

---

## Completion Checklist

### Validation Classes:
- [ ] ValidationException with structured error handling
- [ ] ContentValidator with comprehensive field validation
- [ ] BlockValidator with markdown-specific rules
- [ ] ValidationService as high-level facade

### Validation Features:
- [ ] Input sanitization and normalization
- [ ] Field-specific error reporting
- [ ] Security checks for malicious content
- [ ] Object validation for existing instances
- [ ] Statistics for monitoring and debugging

### Error Handling:
- [ ] Clear, actionable error messages
- [ ] Multiple errors per field support
- [ ] Field-specific error access
- [ ] Structured error format

### Testing and Documentation:
- [ ] Comprehensive test coverage
- [ ] All validation rules tested
- [ ] Error handling verified
- [ ] README updated with usage examples

---

## Next Steps

With Task 5 complete, you now have a robust validation system that ensures data integrity and provides clear error messages. The validation layer works seamlessly with your data classes and repository pattern.

You're ready to move on to **Task 6: Testing Setup**, where you'll implement a comprehensive test suite using PHPUnit to ensure all components work correctly together.

## Troubleshooting

### Common Issues:

**Validation too strict:**
- Adjust validation constants (MAX_LENGTH, etc.) as needed
- Consider making some validations configurable

**Performance with large content:**
- Consider streaming validation for very large content
- Add validation caching if needed

**Encoding issues:**
- Ensure all input is properly UTF-8 encoded
- Test with various character sets

**Security concerns:**
- Review and enhance security checks as needed
- Consider additional sanitization for production use
