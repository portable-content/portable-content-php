# Portable Content PHP

PHP implementation of the portable content system - Phase 1A MVP.

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
src/           # Source code
tests/         # Test files
storage/       # SQLite database files
migrations/    # Database schema migrations
```

## License

MIT License (or your preferred license)
