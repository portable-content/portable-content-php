# Usage Examples

This document provides practical examples for common use cases with the Portable Content PHP library.

## Basic Examples

### 1. Simple Note Creation

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;
use PortableContent\Tests\Support\Repository\RepositoryFactory;

// Create a simple note
$block = MarkdownBlock::create('# My First Note\n\nThis is the content of my note.');
$note = ContentItem::create('note', 'My First Note', 'A simple note example', [$block]);

// Set up repository and save
$repository = RepositoryFactory::createInMemoryRepository();
$repository->save($note);

echo "Created note: {$note->title} (ID: {$note->id})\n";
```

### 2. Multi-Block Article

```php
// Create an article with multiple sections
$introduction = MarkdownBlock::create(
    '# Introduction\n\nWelcome to this comprehensive guide.'
);

$mainContent = MarkdownBlock::create(
    '## Main Content\n\nHere we dive into the details:\n\n- Point 1\n- Point 2\n- Point 3'
);

$conclusion = MarkdownBlock::create(
    '## Conclusion\n\nThanks for reading! We hope this was helpful.'
);

$article = ContentItem::create(
    type: 'article',
    title: 'Complete Guide',
    summary: 'A comprehensive guide with multiple sections',
    blocks: [$introduction, $mainContent, $conclusion]
);

$repository->save($article);
echo "Created article with " . count($article->blocks) . " blocks\n";
```

## Validation Examples

### 3. Form Input Validation

```php
use PortableContent\Validation\ContentValidationService;
use PortableContent\Validation\ContentSanitizer;
use PortableContent\Validation\BlockSanitizerManager;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Block\Markdown\MarkdownBlockSanitizer;
use Symfony\Component\Validator\Validation;

// Set up validation service
$blockSanitizerManager = new BlockSanitizerManager([new MarkdownBlockSanitizer()]);
$contentSanitizer = new ContentSanitizer($blockSanitizerManager);
$symfonyValidator = Validation::createValidator();
$contentValidator = new SymfonyValidatorAdapter($symfonyValidator);
$validationService = new ContentValidationService($contentSanitizer, $contentValidator);

// Simulate form input (messy data)
$formData = [
    'type' => '  blog_post  ',  // Extra whitespace
    'title' => '  My Blog Post  ',
    'summary' => "A post about PHP\r\nwith\n\n\n\nmixed line endings",
    'blocks' => [
        [
            'kind' => '  MARKDOWN  ',  // Wrong case
            'source' => "  # Hello World  \n\n\n\n  This is my **first** blog post!  \n\n  ## Section 1  \n\n  Content here.  "
        ]
    ]
];

// Validate and process
$result = $validationService->validateContentCreation($formData);

if ($result->isValid()) {
    $sanitizedData = $result->getData();
    
    // Create domain objects from validated data
    $blocks = [];
    foreach ($sanitizedData['blocks'] as $blockData) {
        $blocks[] = MarkdownBlock::create($blockData['source']);
    }
    
    $content = ContentItem::create(
        $sanitizedData['type'],
        $sanitizedData['title'],
        $sanitizedData['summary'],
        $blocks
    );
    
    $repository->save($content);
    echo "Blog post created successfully!\n";
    echo "Type: {$content->type}\n";
    echo "Title: {$content->title}\n";
} else {
    echo "Validation failed:\n";
    foreach ($result->getErrors() as $field => $errors) {
        foreach ($errors as $error) {
            echo "- {$field}: {$error}\n";
        }
    }
}
```

### 4. API Endpoint Example

```php
// Example API endpoint for creating content
function createContentEndpoint(array $requestData): array
{
    try {
        // Validate input
        $result = $validationService->validateContentCreation($requestData);
        
        if (!$result->isValid()) {
            return [
                'success' => false,
                'errors' => $result->getErrors(),
                'message' => 'Validation failed'
            ];
        }
        
        // Create content from validated data
        $sanitizedData = $result->getData();
        $blocks = [];
        foreach ($sanitizedData['blocks'] as $blockData) {
            $blocks[] = MarkdownBlock::create($blockData['source']);
        }
        
        $content = ContentItem::create(
            $sanitizedData['type'],
            $sanitizedData['title'] ?? null,
            $sanitizedData['summary'] ?? null,
            $blocks
        );
        
        // Save to repository
        $repository->save($content);
        
        return [
            'success' => true,
            'data' => [
                'id' => $content->id,
                'type' => $content->type,
                'title' => $content->title,
                'summary' => $content->summary,
                'block_count' => count($content->blocks),
                'created_at' => $content->createdAt->format('c')
            ]
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => 'Internal server error',
            'error' => $e->getMessage()
        ];
    }
}

// Usage
$response = createContentEndpoint($_POST);
header('Content-Type: application/json');
echo json_encode($response);
```

## Repository Examples

### 5. CRUD Operations

```php
// Create
$content = ContentItem::create('note', 'Test Note');
$repository->save($content);

// Read
$retrieved = $repository->findById($content->id);
if ($retrieved) {
    echo "Found: {$retrieved->title}\n";
}

// Update (immutable pattern)
$updated = $retrieved->withTitle('Updated Title');
$repository->save($updated);

// Delete
$repository->delete($content->id);

// Verify deletion
$deleted = $repository->findById($content->id);
assert($deleted === null);
```

### 6. Pagination and Listing

```php
// Get first page (20 items)
$firstPage = $repository->findAll(limit: 20, offset: 0);

// Get second page
$secondPage = $repository->findAll(limit: 20, offset: 20);

// Get all items (with higher limit)
$allItems = $repository->findAll(limit: 1000, offset: 0);

echo "First page: " . count($firstPage) . " items\n";
echo "Second page: " . count($secondPage) . " items\n";
echo "Total items: " . count($allItems) . " items\n";
```

### 7. Different Repository Types

```php
// In-memory repository (for testing)
$memoryRepo = RepositoryFactory::createInMemoryRepository();

// SQLite file repository
$fileRepo = RepositoryFactory::createSQLiteRepository('storage/content.db');

// Default repository
$defaultRepo = RepositoryFactory::getDefaultRepository();

// Use the same interface for all
foreach ([$memoryRepo, $fileRepo, $defaultRepo] as $repo) {
    $content = ContentItem::create('test', 'Test Content');
    $repo->save($content);
    $retrieved = $repo->findById($content->id);
    assert($retrieved !== null);
}
```

## Advanced Examples

### 8. Content Migration

```php
// Migrate content from one repository to another
function migrateContent(
    ContentRepositoryInterface $source,
    ContentRepositoryInterface $destination
): int {
    $migrated = 0;
    $offset = 0;
    $batchSize = 50;
    
    do {
        $batch = $source->findAll(limit: $batchSize, offset: $offset);
        
        foreach ($batch as $content) {
            try {
                $destination->save($content);
                $migrated++;
            } catch (RepositoryException $e) {
                echo "Failed to migrate {$content->id}: {$e->getMessage()}\n";
            }
        }
        
        $offset += $batchSize;
    } while (count($batch) === $batchSize);
    
    return $migrated;
}

// Usage
$sourceRepo = RepositoryFactory::createSQLiteRepository('old_database.db');
$destRepo = RepositoryFactory::createSQLiteRepository('new_database.db');
$count = migrateContent($sourceRepo, $destRepo);
echo "Migrated {$count} content items\n";
```

### 9. Content Transformation

```php
// Transform content (e.g., update all notes to articles)
function transformContentType(
    ContentRepositoryInterface $repository,
    string $fromType,
    string $toType
): int {
    $transformed = 0;
    $allContent = $repository->findAll(limit: 1000);
    
    foreach ($allContent as $content) {
        if ($content->type === $fromType) {
            // Create new content with updated type
            $updated = new ContentItem(
                id: $content->id,
                type: $toType,
                title: $content->title,
                summary: $content->summary,
                blocks: $content->blocks,
                createdAt: $content->createdAt,
                updatedAt: new DateTimeImmutable()
            );
            
            $repository->save($updated);
            $transformed++;
        }
    }
    
    return $transformed;
}

// Usage
$count = transformContentType($repository, 'note', 'article');
echo "Transformed {$count} notes to articles\n";
```

### 10. Bulk Content Creation

```php
// Create multiple content items efficiently
function createBulkContent(
    ContentRepositoryInterface $repository,
    array $contentData
): array {
    $created = [];
    $errors = [];
    
    foreach ($contentData as $index => $data) {
        try {
            // Validate each item
            $result = $validationService->validateContentCreation($data);
            
            if (!$result->isValid()) {
                $errors[$index] = $result->getErrors();
                continue;
            }
            
            // Create content
            $sanitizedData = $result->getData();
            $blocks = [];
            foreach ($sanitizedData['blocks'] as $blockData) {
                $blocks[] = MarkdownBlock::create($blockData['source']);
            }
            
            $content = ContentItem::create(
                $sanitizedData['type'],
                $sanitizedData['title'] ?? null,
                $sanitizedData['summary'] ?? null,
                $blocks
            );
            
            $repository->save($content);
            $created[] = $content->id;
            
        } catch (Exception $e) {
            $errors[$index] = ['general' => [$e->getMessage()]];
        }
    }
    
    return [
        'created' => $created,
        'errors' => $errors,
        'success_count' => count($created),
        'error_count' => count($errors)
    ];
}

// Usage
$bulkData = [
    [
        'type' => 'note',
        'title' => 'Note 1',
        'blocks' => [['kind' => 'markdown', 'source' => '# Content 1']]
    ],
    [
        'type' => 'note',
        'title' => 'Note 2',
        'blocks' => [['kind' => 'markdown', 'source' => '# Content 2']]
    ]
];

$result = createBulkContent($repository, $bulkData);
echo "Created: {$result['success_count']}, Errors: {$result['error_count']}\n";
```

## Error Handling Examples

### 11. Comprehensive Error Handling

```php
use PortableContent\Exception\InvalidContentException;
use PortableContent\Exception\RepositoryException;
use PortableContent\Exception\ValidationException;

function safeContentCreation(array $data): array
{
    try {
        // Validate input
        $result = $validationService->validateContentCreation($data);
        
        if (!$result->isValid()) {
            return [
                'success' => false,
                'type' => 'validation_error',
                'errors' => $result->getErrors()
            ];
        }
        
        // Create domain objects
        $sanitizedData = $result->getData();
        $blocks = [];
        foreach ($sanitizedData['blocks'] as $blockData) {
            $blocks[] = MarkdownBlock::create($blockData['source']);
        }
        
        $content = ContentItem::create(
            $sanitizedData['type'],
            $sanitizedData['title'] ?? null,
            $sanitizedData['summary'] ?? null,
            $blocks
        );
        
        // Save to repository
        $repository->save($content);
        
        return [
            'success' => true,
            'content_id' => $content->id
        ];
        
    } catch (InvalidContentException $e) {
        return [
            'success' => false,
            'type' => 'invalid_content',
            'message' => $e->getMessage()
        ];
    } catch (RepositoryException $e) {
        return [
            'success' => false,
            'type' => 'repository_error',
            'message' => 'Failed to save content'
        ];
    } catch (Exception $e) {
        return [
            'success' => false,
            'type' => 'unknown_error',
            'message' => 'An unexpected error occurred'
        ];
    }
}
```

### 12. Validation with Custom Messages

```php
function validateWithCustomMessages(array $data): array
{
    $result = $validationService->validateContentCreation($data);
    
    if ($result->isValid()) {
        return ['valid' => true, 'data' => $result->getData()];
    }
    
    // Transform error messages
    $customMessages = [];
    foreach ($result->getErrors() as $field => $errors) {
        switch ($field) {
            case 'type':
                $customMessages['type'] = 'Please provide a valid content type (letters, numbers, and underscores only).';
                break;
            case 'title':
                $customMessages['title'] = 'Title is too long. Please keep it under 255 characters.';
                break;
            case 'blocks':
                $customMessages['content'] = 'Please provide at least one content block with valid markdown.';
                break;
            default:
                $customMessages[$field] = implode(' ', $errors);
        }
    }
    
    return ['valid' => false, 'messages' => $customMessages];
}
```

## Testing Examples

### 13. Unit Testing with Repository

```php
use PHPUnit\Framework\TestCase;

class ContentServiceTest extends TestCase
{
    private ContentRepositoryInterface $repository;
    
    protected function setUp(): void
    {
        // Use in-memory repository for testing
        $this->repository = RepositoryFactory::createInMemoryRepository();
    }
    
    public function testCreateAndRetrieveContent(): void
    {
        $block = MarkdownBlock::create('# Test Content');
        $content = ContentItem::create('test', 'Test Title', null, [$block]);
        
        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);
        
        $this->assertNotNull($retrieved);
        $this->assertEquals($content->title, $retrieved->title);
        $this->assertCount(1, $retrieved->blocks);
    }
}
```

These examples demonstrate the flexibility and power of the Portable Content PHP library. The consistent API design makes it easy to work with content across different scenarios while maintaining type safety and data integrity.
