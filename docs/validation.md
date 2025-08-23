# Validation System

The Portable Content PHP library includes a comprehensive validation system that handles input sanitization and validation through a multi-stage pipeline.

## Overview

The validation system follows this flow:

```
Raw Input → Sanitization → Validation → Domain Objects
```

### Key Components

1. **ContentSanitizer** - Cleans and normalizes input data
2. **ContentValidator** - Validates sanitized data against business rules
3. **ContentValidationService** - Orchestrates the complete pipeline
4. **ValidationResult** - Encapsulates validation outcomes

## Sanitization

Sanitization cleans and normalizes input data before validation.

### Content-Level Sanitization

```php
use PortableContent\Validation\ContentSanitizer;
use PortableContent\Validation\BlockSanitizerManager;
use PortableContent\Block\Markdown\MarkdownBlockSanitizer;

$blockSanitizerManager = new BlockSanitizerManager([
    new MarkdownBlockSanitizer()
]);
$sanitizer = new ContentSanitizer($blockSanitizerManager);

$rawData = [
    'type' => '  NOTE  ',  // Whitespace and case issues
    'title' => '  My Title  ',
    'summary' => "Line 1\r\nLine 2\n\n\n\nToo many newlines",
    'blocks' => [
        [
            'kind' => '  MARKDOWN  ',
            'source' => '  # Title  \n\n\n\n  Content  '
        ]
    ]
];

$sanitized = $sanitizer->sanitize($rawData);
// Result:
// [
//     'type' => 'NOTE',
//     'title' => 'My Title',
//     'summary' => "Line 1\nLine 2\n\nToo many newlines",
//     'blocks' => [
//         [
//             'kind' => 'markdown',
//             'source' => '# Title\n\nContent'
//         ]
//     ]
// ]
```

### Sanitization Rules

#### Type Field
- Trims whitespace
- Removes non-alphanumeric characters (except underscores)
- Preserves case

#### Title Field
- Trims whitespace
- Removes control characters
- Normalizes line endings
- Limits consecutive newlines

#### Summary Field
- Trims whitespace
- Removes control characters
- Normalizes line endings to Unix style
- Limits consecutive newlines to maximum of 2

#### Block Sanitization
- Delegates to block-specific sanitizers
- Markdown blocks: removes trailing whitespace, normalizes line endings, limits blank lines

### Block Sanitizer Registry

```php
use PortableContent\Validation\BlockSanitizerManager;

$manager = new BlockSanitizerManager();

// Register sanitizers
$manager->register(new MarkdownBlockSanitizer());

// Check if sanitizer exists
if ($manager->hasSanitizer('markdown')) {
    $sanitizer = $manager->getSanitizer('markdown');
}

// Sanitize multiple blocks
$sanitizedBlocks = $manager->sanitizeBlocks($rawBlocks);
```

## Validation

Validation ensures sanitized data meets business rules and constraints.

### Content-Level Validation

```php
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use Symfony\Component\Validator\Validation;

$symfonyValidator = Validation::createValidator();
$validator = new SymfonyValidatorAdapter($symfonyValidator);

$result = $validator->validateContentCreation($sanitizedData);

if ($result->isValid()) {
    $validData = $result->getData();
} else {
    $errors = $result->getErrors();
}
```

### Validation Rules

#### Content Creation Rules
- **type**: Required, non-empty, max 50 chars, alphanumeric + underscore only
- **title**: Optional, max 255 chars if provided
- **summary**: Optional, max 1000 chars if provided
- **blocks**: Required, at least 1 block, max 10 blocks, all must be valid

#### Content Update Rules
- All fields optional (allows partial updates)
- Same constraints as creation when fields are provided

#### Block Validation Rules
- **kind**: Required, must be 'markdown' (extensible for future block types)
- **source**: Required, non-empty after trimming, max 100KB

### Custom Validation

```php
use PortableContent\Validation\BlockValidatorManager;
use PortableContent\Block\Markdown\MarkdownBlockValidator;

$blockValidatorManager = new BlockValidatorManager([
    new MarkdownBlockValidator()
]);

$validator = new SymfonyValidatorAdapter($symfonyValidator, $blockValidatorManager);
```

## Complete Validation Pipeline

### Using ContentValidationService

```php
use PortableContent\Validation\ContentValidationService;

$validationService = new ContentValidationService($sanitizer, $validator);

// For content creation
$result = $validationService->validateContentCreation($rawData);

// For content updates
$result = $validationService->validateContentUpdate($partialData);

// Sanitization only
$sanitized = $validationService->sanitizeContent($rawData);

// Validation only (pre-sanitized data)
$result = $validationService->validateSanitizedContent($cleanData);
```

### Detailed Processing

```php
$details = $validationService->processContentWithDetails($rawData);

// Access detailed information
$sanitizedData = $details['sanitized_data'];
$stats = $details['sanitization_stats'];
$validationResult = $details['validation_result'];
$finalResult = $details['final_result'];

// Sanitization statistics
echo "Fields processed: {$stats['fields_processed']}\n";
echo "Fields modified: {$stats['fields_modified']}\n";
echo "Blocks processed: {$stats['blocks_processed']}\n";
```

## Working with ValidationResult

### Checking Results

```php
$result = $validationService->validateContentCreation($data);

// Basic validation check
if ($result->isValid()) {
    $data = $result->getData();
    // Process valid data
} else {
    // Handle errors
    $errors = $result->getErrors();
}
```

### Error Handling

```php
// Get all errors
$allErrors = $result->getErrors();
// Format: ['field' => ['error1', 'error2'], ...]

// Check specific field
if ($result->hasFieldErrors('title')) {
    $titleErrors = $result->getFieldErrors('title');
    foreach ($titleErrors as $error) {
        echo "Title error: {$error}\n";
    }
}

// Get first error (for simple error display)
$firstError = $result->getFirstError();
if ($firstError) {
    echo "Error: {$firstError}\n";
}
```

### Creating ValidationResult

```php
// Success
$success = ValidationResult::success();
$successWithData = ValidationResult::successWithData($validatedData);

// Errors
$singleError = ValidationResult::singleError('title', 'Title is required');
$multipleErrors = ValidationResult::multipleErrors([
    'title' => ['Title is required'],
    'type' => ['Type must be alphanumeric']
]);
```

## Error Messages

### Standard Error Messages

- **Type Validation**:
  - "Type is required"
  - "Type must be 50 characters or less"
  - "Type must contain only letters, numbers, and underscores"

- **Title Validation**:
  - "Title must be 255 characters or less"

- **Summary Validation**:
  - "Summary must be 1000 characters or less"

- **Block Validation**:
  - "At least one block is required"
  - "Maximum 10 blocks allowed"
  - "Block kind is required"
  - "Block kind must be 'markdown'"
  - "Block source is required"
  - "Block source must be 100KB or less"

### Sanitization Errors

- "Invalid blocks data: expected array, got {type}"
- "No sanitizer registered for block type: {type}"
- "Block data must contain a valid 'kind' field"

## Advanced Usage

### Custom Sanitizer

```php
use PortableContent\Contracts\BlockSanitizerInterface;

class CustomBlockSanitizer implements BlockSanitizerInterface
{
    public function sanitize(array $blockData): array
    {
        // Custom sanitization logic
        return $sanitizedData;
    }
    
    public function supports(string $blockType): bool
    {
        return $blockType === 'custom';
    }
    
    public function getBlockType(): string
    {
        return 'custom';
    }
}

// Register custom sanitizer
$blockSanitizerManager->register(new CustomBlockSanitizer());
```

### Custom Validator

```php
use PortableContent\Contracts\BlockValidatorInterface;

class CustomBlockValidator implements BlockValidatorInterface
{
    public function validate(array $blockData): void
    {
        // Custom validation logic
        if (!$this->isValid($blockData)) {
            throw new ValidationException('Custom validation failed');
        }
    }
    
    public function getBlockType(): string
    {
        return 'custom';
    }
}

// Register custom validator
$blockValidatorManager->register(new CustomBlockValidator());
```

### Validation Statistics

```php
$stats = $validationService->getSanitizationStats($originalData, $sanitizedData);

// Available statistics
$stats['fields_processed'];        // Number of fields processed
$stats['fields_modified'];         // Number of fields that were changed
$stats['blocks_processed'];        // Number of blocks processed
$stats['total_content_length_before']; // Total content length before sanitization
$stats['total_content_length_after'];  // Total content length after sanitization
```

## Best Practices

### 1. Always Validate User Input

```php
// Good: Validate all user input
$result = $validationService->validateContentCreation($userInput);
if ($result->isValid()) {
    // Process valid data
}

// Bad: Skip validation
$content = ContentItem::create($userInput['type'], $userInput['title']);
```

### 2. Handle Validation Errors Gracefully

```php
$result = $validationService->validateContentCreation($data);

if (!$result->isValid()) {
    $errors = $result->getErrors();
    
    // Return structured error response
    return [
        'success' => false,
        'errors' => $errors,
        'message' => 'Validation failed'
    ];
}
```

### 3. Use Appropriate Validation Method

```php
// For new content
$result = $validationService->validateContentCreation($data);

// For updates (allows partial data)
$result = $validationService->validateContentUpdate($partialData);

// For pre-sanitized data
$result = $validationService->validateSanitizedContent($cleanData);
```

### 4. Monitor Sanitization Changes

```php
$details = $validationService->processContentWithDetails($data);
$stats = $details['sanitization_stats'];

if ($stats['fields_modified'] > 0) {
    // Log sanitization changes for monitoring
    error_log("Sanitization modified {$stats['fields_modified']} fields");
}
```
