# Getting Started with Portable Content PHP

This guide will help you get up and running with the Portable Content PHP library quickly.

## Installation

### Requirements

- PHP 8.3 or higher
- Composer
- SQLite (included with PHP)

### Install via Composer

```bash
composer require portable-content/portable-content-php
```

### Or Clone from GitHub

```bash
git clone https://github.com/portable-content/portable-content-php.git
cd portable-content-php
composer install
```

## Database Setup

### Initialize Database

```bash
# Create database with migrations
composer migrate

# Or specify custom path
php bin/migrate.php --path=storage/custom.db

# Test with in-memory database (for development)
composer migrate-test
```

### Database Schema

The system uses SQLite with two main tables:

- **content_items**: Stores ContentItem metadata (id, type, title, summary, timestamps)
- **markdown_blocks**: Stores MarkdownBlock content (id, content_id, source, timestamp)

Foreign key constraints ensure data integrity between content and blocks.

## Basic Usage

### 1. Simple Content Creation

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;

// Create a markdown block
$block = MarkdownBlock::create('# Hello World\n\nThis is my first note!');

// Create content with the block
$content = ContentItem::create(
    type: 'note',
    title: 'My First Note',
    summary: 'A simple example',
    blocks: [$block]
);

echo "Created content: {$content->getTitle()}\n";
echo "Content ID: {$content->getId()}\n";
echo "Block count: " . count($content->getBlocks()) . "\n";
```

### 2. Repository Usage

```php
use PortableContent\Tests\Support\Repository\RepositoryFactory;

// Create repository (in-memory for testing)
$repository = RepositoryFactory::createInMemoryRepository();

// Or use SQLite file
$repository = RepositoryFactory::createSQLiteRepository('storage/content.db');

// Save content
$repository->save($content);

// Retrieve by ID
$retrieved = $repository->findById($content->getId());

// List all content (with pagination)
$allContent = $repository->findAll(limit: 10, offset: 0);

// Delete content
$repository->delete($content->getId());
```

### 3. Working with Multiple Blocks

```php
// Create multiple blocks
$introBlock = MarkdownBlock::create('# Introduction\n\nWelcome to my article.');
$contentBlock = MarkdownBlock::create('## Main Content\n\nHere is the main content.');
$conclusionBlock = MarkdownBlock::create('## Conclusion\n\nThanks for reading!');

// Create content with multiple blocks
$article = ContentItem::create(
    type: 'article',
    title: 'My Article',
    summary: 'An article with multiple sections',
    blocks: [$introBlock, $contentBlock, $conclusionBlock]
);

// Save and retrieve
$repository->save($article);
$retrieved = $repository->findById($article->getId());

echo "Article has " . count($retrieved->getBlocks()) . " blocks\n";
```

### 4. Mutable Updates

```php
// Content objects are mutable - you can modify them directly
$content = ContentItem::create('note', 'Original Title');

// Update title
$content->setTitle('Updated Title');

// Update blocks
$newBlock = MarkdownBlock::create('# Updated Content');
$content->setBlocks([$newBlock]);

// Add a block
$additionalBlock = MarkdownBlock::create('## Additional Section');
$content->addBlock($additionalBlock);

// Modify existing blocks
$existingBlock = $content->getBlocks()[0];
$existingBlock->setSource('# Modified Content');

// Save the updated version
$repository->save($content);
```

## Input Validation and Sanitization

### Basic Validation

```php
use PortableContent\Validation\ContentValidationService;
use PortableContent\Validation\ContentSanitizer;
use PortableContent\Validation\BlockSanitizerManager;
use PortableContent\Validation\Adapters\SymfonyValidatorAdapter;
use PortableContent\Block\Markdown\MarkdownBlockSanitizer;
use Symfony\Component\Validator\Validation;

// Set up validation components
$blockSanitizerManager = new BlockSanitizerManager([
    new MarkdownBlockSanitizer()
]);
$contentSanitizer = new ContentSanitizer($blockSanitizerManager);

$symfonyValidator = Validation::createValidator();
$contentValidator = new SymfonyValidatorAdapter($symfonyValidator);

$validationService = new ContentValidationService($contentSanitizer, $contentValidator);
```

### Validating Raw Input

```php
// Raw input data (as from API/form submission)
$rawData = [
    'type' => '  note  ',  // Has whitespace
    'title' => 'My Note',
    'summary' => "A note\r\nwith\n\n\nmixed line endings",
    'blocks' => [
        [
            'kind' => '  MARKDOWN  ',  // Wrong case and whitespace
            'source' => '  # Hello World  \n\n  Content here.  '
        ]
    ]
];

// Validate and sanitize
$result = $validationService->validateContentCreation($rawData);

if ($result->isValid()) {
    $sanitizedData = $result->getData();
    
    // Create domain objects from clean data
    $blocks = [];
    foreach ($sanitizedData['blocks'] as $blockData) {
        $blocks[] = MarkdownBlock::create($blockData['source']);
    }
    
    $content = ContentItem::create(
        $sanitizedData['type'],
        $sanitizedData['title'],
        $sanitizedData['summary'] ?? null,
        $blocks
    );
    
    $repository->save($content);
    echo "Content saved successfully!\n";
} else {
    $errors = $result->getErrors();
    echo "Validation failed:\n";
    foreach ($errors as $field => $fieldErrors) {
        foreach ($fieldErrors as $error) {
            echo "- {$field}: {$error}\n";
        }
    }
}
```

### Error Handling

```php
use PortableContent\Exception\ValidationException;
use PortableContent\Exception\RepositoryException;
use PortableContent\Exception\InvalidContentException;

try {
    // Attempt to create content
    $content = ContentItem::create('', 'Title'); // Empty type will fail
} catch (InvalidContentException $e) {
    echo "Invalid content: " . $e->getMessage() . "\n";
}

try {
    // Attempt to save content
    $repository->save($content);
} catch (RepositoryException $e) {
    echo "Repository error: " . $e->getMessage() . "\n";
}
```

## Next Steps

- Read the [API Reference](api-reference.md) for detailed method documentation
- Learn about the [Validation System](validation.md) for advanced input handling
- Explore [Examples](examples.md) for common usage patterns
- Understand the [Architecture](architecture.md) for system design details

## Common Patterns

### Factory Pattern for Repository

```php
// Create different repository types
$inMemoryRepo = RepositoryFactory::createInMemoryRepository();
$sqliteRepo = RepositoryFactory::createSQLiteRepository('path/to/db.sqlite');
$defaultRepo = RepositoryFactory::getDefaultRepository();
```

### Validation with Custom Rules

```php
// For updates (allows partial data)
$updateResult = $validationService->validateContentUpdate($partialData);

// For sanitization only
$sanitizedData = $validationService->sanitizeContent($rawData);

// For validation only (pre-sanitized data)
$validationResult = $validationService->validateSanitizedContent($cleanData);
```

### Working with Validation Results

```php
$result = $validationService->validateContentCreation($data);

// Check if valid
if ($result->isValid()) {
    $data = $result->getData();
    // Process valid data
}

// Get all errors
$allErrors = $result->getErrors();

// Check specific field errors
if ($result->hasFieldErrors('title')) {
    $titleErrors = $result->getFieldErrors('title');
}

// Get first error message
$firstError = $result->getFirstError();
```
