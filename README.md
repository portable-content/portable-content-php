# Portable Content PHP

[![Version](https://img.shields.io/badge/version-0.3.0-blue.svg)](https://github.com/portable-content/portable-content-php/releases/tag/v0.3.0)
[![codecov](https://codecov.io/gh/portable-content/portable-content-php/graph/badge.svg?token=V5i88ShX88)](https://codecov.io/gh/portable-content/portable-content-php)
![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-blue)
![PHPStan Level](https://img.shields.io/badge/PHPStan-Level%209-brightgreen)
![Tests](https://img.shields.io/badge/Tests-315%20passing-brightgreen)
![License](https://img.shields.io/badge/License-Apache%202.0-blue)

A robust PHP library for managing portable content with comprehensive validation, sanitization, and flexible storage backends.

## 🎉 v0.2.0 Released - Refined Architecture!

This release represents a **strategic refinement** of the repository architecture, focusing on a robust SQLite foundation while removing speculative implementations. The result is a cleaner, more maintainable codebase ready for production use.

### ✨ Key Features

- **🏗️ Immutable Domain Objects** - Thread-safe ContentItem and MarkdownBlock entities
- **🔒 Type-Safe Validation** - Comprehensive input validation and sanitization
- **💾 Repository Pattern** - Clean abstraction with capability discovery system
- **🧪 Comprehensive Testing** - 315 tests with 1,674 assertions
- **📚 Complete Documentation** - 7 detailed guides covering all aspects
- **⚡ Production Ready** - PHPStan Level 9, zero static analysis errors
- **🧹 Clean Architecture** - Focused, maintainable codebase without over-engineering

### 🎯 v0.2.0 Improvements ✅

- ✅ **Repository Capabilities** - Added feature discovery with `getCapabilities()` and `supports()`
- ✅ **Simplified Architecture** - Removed speculative vector database implementations
- ✅ **Enhanced SQLite Repository** - Production-ready with comprehensive error handling
- ✅ **Library Best Practices** - Proper dependency management and distribution
- ✅ **Cleaner Codebase** - Focused implementation without over-engineering
- ✅ **Maintained Quality** - All 315 tests passing, PHPStan Level 9 compliance

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

Phase 1B will add a GraphQL API layer on top of this solid foundation. See [docs/future-features.md](docs/future-features.md) for the complete roadmap.

## 📦 Installation

```bash
composer require portable-content/portable-content-php
```

Or clone the repository:

```bash
git clone https://github.com/portable-content/portable-content-php.git
cd portable-content-php
composer install
```

## 🚀 Release Information

### v0.3.0 - Entity Architecture
- **Release Date**: January 24, 2025
- **Status**: Production Ready
- **PHP Compatibility**: 8.3+
- **Quality**: PHPStan Level 9, Zero Static Analysis Errors
- **Testing**: 315 Tests, 1,673 Assertions
- **Documentation**: Complete (7 Guides)
- **Breaking Change**: ContentItem converted from value object to entity

See [CHANGELOG.md](CHANGELOG.md) for detailed release notes.

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
