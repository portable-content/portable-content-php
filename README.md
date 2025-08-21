# Portable Content PHP

PHP implementation of the portable content system

[![codecov](https://codecov.io/gh/portable-content/portable-content-php/graph/badge.svg?token=V5i88ShX88)](https://codecov.io/gh/portable-content/portable-content-php)

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

## Usage

*Coming soon - Phase 1A implementation in progress*

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
tests/                        # Test files
├── Support/                  # Test support utilities
│   ├── Database/            # SQLite database helpers (for testing)
│   └── migrations/          # Test database schema migrations
├── Unit/                    # Unit tests
└── Integration/             # Integration tests
storage/                     # SQLite database files (for development)
bin/                         # CLI tools
└── migrate.php              # Database migration tool
```

## License

Apache 2.0 License
