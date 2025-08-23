# Portable Content PHP

PHP implementation of the portable content system

[![codecov](https://codecov.io/gh/portable-content/portable-content-php/graph/badge.svg?token=V5i88ShX88)](https://codecov.io/gh/portable-content/portable-content-php)
![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%209-brightgreen)
![Tests](https://img.shields.io/badge/Tests-315%20passing-brightgreen)
![License](https://img.shields.io/badge/License-Apache%202.0-blue)

## Overview

This is a minimal viable implementation focusing on markdown content storage and retrieval using SQLite. Part of the larger portable content system design.

## Phase 1A Goals

- ✅ Basic content entity storage (ContentItem, MarkdownBlock)
- ✅ SQLite database with simple schema
- ✅ Repository pattern for data access
- ✅ Input validation
- ✅ Comprehensive testing

## Requirements

- PHP 8.3 or higher
- Composer
- SQLite (included with PHP)

## Installation

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

# Test with in-memory database
composer migrate-test
```

### Database Schema

The system uses SQLite with two main tables:

- **content_items**: Stores ContentItem metadata (id, type, title, summary, timestamps)
- **markdown_blocks**: Stores MarkdownBlock content (id, content_id, source, timestamp)

Foreign key constraints ensure data integrity between content and blocks.

## Quick Start

### Basic Usage

```php
<?php

require_once 'vendor/autoload.php';

use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;
use PortableContent\Tests\Support\Repository\RepositoryFactory;

// Create a markdown block
$block = MarkdownBlock::create('# Hello World\n\nThis is my first note!');

// Create content with the block
$content = ContentItem::create('note', 'My First Note', 'A simple example', [$block]);

// Set up repository (in-memory for this example)
$repository = RepositoryFactory::createInMemoryRepository();

// Save content
$repository->save($content);

// Retrieve content
$retrieved = $repository->findById($content->id);
echo "Retrieved: {$retrieved->title}\n";
```

### With Validation

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

// Raw input data (as from API/form)
$rawData = [
    'type' => '  note  ',  // Will be sanitized
    'title' => 'My Note',
    'blocks' => [
        [
            'kind' => 'markdown',
            'source' => '# Hello World\n\nContent here.'
        ]
    ]
];

// Validate and sanitize
$result = $validationService->validateContentCreation($rawData);

if ($result->isValid()) {
    $sanitizedData = $result->getData();
    // Create domain objects from validated data...
} else {
    $errors = $result->getErrors();
    // Handle validation errors...
}
```

## Table of Contents

- [Installation](#installation)
- [Database Setup](#database-setup)
- [Quick Start](#quick-start)
- [Documentation](#documentation)
- [Development](#development)
- [Project Structure](#project-structure)
- [Features](#features)
- [Requirements](#requirements)
- [License](#license)

## Features

✅ **Immutable Domain Objects** - Thread-safe, predictable content management
✅ **Type-Safe API** - Full PHP 8.3+ type hints and strict typing
✅ **Input Validation** - Comprehensive sanitization and validation pipeline
✅ **Repository Pattern** - Clean data access abstraction
✅ **Transaction Safety** - ACID-compliant database operations
✅ **Extensible Blocks** - Plugin system for different content types
✅ **Production Ready** - 315+ tests, PHPStan Level 9, CI/CD pipeline
✅ **Developer Friendly** - Comprehensive documentation and examples

## Documentation

For complete documentation, see the [docs/](docs/) directory:

- **[Getting Started Guide](docs/getting-started.md)** - Complete setup and basic usage
- **[API Reference](docs/api-reference.md)** - Detailed API documentation
- **[Validation System](docs/validation.md)** - Input validation and sanitization
- **[Repository Pattern](docs/repository.md)** - Data persistence and retrieval
- **[Architecture Overview](docs/architecture.md)** - System design and components
- **[Examples](docs/examples.md)** - Common usage patterns and recipes
- **[Future Features](docs/future-features.md)** - Planned features and roadmap

### For AI/LLM Developers

- **[llms.txt](llms.txt)** - Essential guide for Large Language Models working with this library

## Development

```bash
# Run tests
composer test

# Run tests with coverage
composer test-coverage

# Run specific test suite
./vendor/bin/phpunit --testsuite=Unit

# Code quality checks
composer cs-check          # Check code style
composer cs-fix            # Fix code style
composer phpstan           # Run static analysis
composer security-audit    # Check for security issues

# Composer maintenance
composer composer-normalize # Check composer.json format
composer composer-normalize-fix # Fix composer.json format
```

## Project Structure

```
src/                          # Source code
├── Block/                   # Block implementations (MarkdownBlock)
├── Contracts/               # Interfaces and contracts
├── Exception/               # Exception classes
├── Validation/              # Validation and sanitization system
└── ContentItem.php          # Main domain object
tests/                        # Test files (315 tests, 1,283 assertions)
├── Support/                  # Test support utilities
│   ├── Database/            # SQLite database helpers
│   └── Repository/          # Repository factory for testing
├── Unit/                    # Unit tests
└── Integration/             # Integration tests (end-to-end workflows)
docs/                        # Complete documentation
├── getting-started.md       # Setup and basic usage
├── api-reference.md         # Complete API documentation
├── validation.md            # Validation system guide
├── examples.md              # Usage examples and patterns
├── architecture.md          # System design and components
├── repository.md            # Repository pattern details
└── future-features.md       # Roadmap and planned features
storage/                     # SQLite database files
bin/                         # CLI tools
└── migrate.php              # Database migration tool
migrations/                  # Database schema migrations
llms.txt                     # Guide for AI/LLM developers
```

## Development Status

**Phase 1A: COMPLETE ✅**

This library represents the completed Phase 1A implementation with all goals achieved:

- ✅ Basic content entity storage (ContentItem, MarkdownBlock)
- ✅ SQLite database with proper schema and migrations
- ✅ Repository pattern for data access
- ✅ Comprehensive input validation and sanitization
- ✅ Extensive testing (315+ tests, 1,283+ assertions)
- ✅ Production-ready code quality (PHPStan Level 9, CS-Fixer)

**Next Phase: GraphQL API**

Phase 1B will add a GraphQL API layer on top of this solid foundation.

## Contributing

This library follows strict quality standards:

- **PHP 8.3+** with strict typing
- **PHPStan Level 9** static analysis
- **PHP-CS-Fixer** code style compliance
- **Comprehensive testing** with unit and integration tests
- **Immutable design** patterns throughout
- **Clean architecture** with clear separation of concerns

## License

Apache 2.0 License
