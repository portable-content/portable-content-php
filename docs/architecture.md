# Architecture Overview

This document describes the architecture and design principles of the Portable Content PHP library.

## Design Principles

### 1. Immutability
All domain objects are immutable, providing thread safety and preventing accidental mutations.

```php
// Content objects are immutable
$content = ContentItem::create('note', 'Original Title');
$updated = $content->withTitle('New Title'); // Creates new instance
```

### 2. Type Safety
Extensive use of PHP 8.3+ type hints and strict typing for compile-time error detection.

```php
declare(strict_types=1);

public function save(ContentItem $content): void // Strict typing
```

### 3. Separation of Concerns
Clear separation between domain logic, validation, persistence, and presentation layers.

### 4. Dependency Injection
Components depend on interfaces, not concrete implementations, enabling easy testing and extensibility.

```php
public function __construct(
    private readonly SanitizerInterface $sanitizer,
    private readonly ContentValidatorInterface $validator
) {}
```

## System Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                    Presentation Layer                       │
│                   (API, CLI, Web UI)                       │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                 Application Layer                           │
│              (Validation Service)                          │
│  ┌─────────────────┐    ┌─────────────────────────────────┐ │
│  │   Sanitization  │    │        Validation               │ │
│  │     Pipeline    │    │        Pipeline                 │ │
│  └─────────────────┘    └─────────────────────────────────┘ │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                   Domain Layer                              │
│  ┌─────────────────┐    ┌─────────────────────────────────┐ │
│  │   ContentItem   │    │      Block Types                │ │
│  │   (Aggregate)   │    │   (MarkdownBlock, etc.)        │ │
│  └─────────────────┘    └─────────────────────────────────┘ │
└─────────────────────┬───────────────────────────────────────┘
                      │
┌─────────────────────▼───────────────────────────────────────┐
│                Infrastructure Layer                         │
│  ┌─────────────────┐    ┌─────────────────────────────────┐ │
│  │   Repository    │    │       Database                  │ │
│  │   (SQLite)      │    │      (SQLite)                  │ │
│  └─────────────────┘    └─────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────┘
```

## Core Components

### Domain Layer

#### ContentItem (Aggregate Root)
- Central domain entity representing a piece of content
- Contains metadata (type, title, summary) and blocks
- Immutable with factory methods for creation and updates
- Enforces business rules and invariants

#### Block System
- **BlockInterface**: Contract for all block types
- **MarkdownBlock**: Concrete implementation for markdown content
- Extensible design for future block types (HTML, Code, etc.)

#### Value Objects
- **ValidationResult**: Encapsulates validation outcomes
- **ContentCreationRequest**: Type-safe DTO for validated input
- **BlockData**: Structured block information

### Application Layer

#### ContentValidationService
- Orchestrates the complete validation pipeline
- Coordinates sanitization and validation processes
- Provides detailed processing information and statistics

#### Sanitization System
- **ContentSanitizer**: Main sanitization coordinator
- **BlockSanitizerManager**: Registry for block-specific sanitizers
- **MarkdownBlockSanitizer**: Markdown-specific sanitization rules

#### Validation System
- **SymfonyValidatorAdapter**: Adapter for Symfony Validator
- **BlockValidatorManager**: Registry for block-specific validators
- **MarkdownBlockValidator**: Markdown-specific validation rules

### Infrastructure Layer

#### Repository Pattern
- **ContentRepositoryInterface**: Contract for persistence operations
- **SQLiteContentRepository**: SQLite implementation
- **RepositoryFactory**: Factory for creating repository instances

#### Database Layer
- **Database**: Connection management and migrations
- **Migration System**: Schema versioning and updates
- **Transaction Management**: ACID compliance for data operations

## Data Flow

### Content Creation Flow

```
1. Raw Input (API/Form)
   ↓
2. ContentValidationService.validateContentCreation()
   ↓
3. ContentSanitizer.sanitize()
   ├── Content-level sanitization (type, title, summary)
   └── Block-level sanitization (via BlockSanitizerManager)
   ↓
4. ContentValidator.validateContentCreation()
   ├── Content-level validation (Symfony constraints)
   └── Block-level validation (via BlockValidatorManager)
   ↓
5. ValidationResult (success/failure with sanitized data)
   ↓
6. Domain Object Creation
   ├── MarkdownBlock.create() for each block
   └── ContentItem.create() with blocks
   ↓
7. Repository.save()
   ├── Begin transaction
   ├── Save content_items record
   ├── Save markdown_blocks records
   └── Commit transaction
   ↓
8. Persisted Content
```

### Content Retrieval Flow

```
1. Repository.findById(id)
   ↓
2. Query content_items table
   ↓
3. Query markdown_blocks table
   ↓
4. Reconstruct domain objects
   ├── Create MarkdownBlock instances
   └── Create ContentItem with blocks
   ↓
5. Return ContentItem
```

## Extension Points

### 1. New Block Types

```php
// 1. Create block class
class CodeBlock implements BlockInterface
{
    public function __construct(
        public readonly string $id,
        public readonly string $language,
        public readonly string $code,
        public readonly DateTimeImmutable $createdAt,
    ) {}
}

// 2. Create sanitizer
class CodeBlockSanitizer implements BlockSanitizerInterface
{
    public function sanitize(array $blockData): array { /* ... */ }
    public function supports(string $blockType): bool { return $blockType === 'code'; }
}

// 3. Create validator
class CodeBlockValidator implements BlockValidatorInterface
{
    public function validate(array $blockData): void { /* ... */ }
    public function getBlockType(): string { return 'code'; }
}

// 4. Register components
$blockSanitizerManager->register(new CodeBlockSanitizer());
$blockValidatorManager->register(new CodeBlockValidator());
```

### 2. Custom Repository Implementation

```php
class PostgreSQLContentRepository implements ContentRepositoryInterface
{
    public function save(ContentItem $content): void { /* PostgreSQL implementation */ }
    public function findById(string $id): ?ContentItem { /* PostgreSQL implementation */ }
    public function findAll(int $limit = 20, int $offset = 0): array { /* PostgreSQL implementation */ }
    public function delete(string $id): void { /* PostgreSQL implementation */ }
}
```

### 3. Custom Validation Adapter

```php
class CustomValidatorAdapter implements ContentValidatorInterface
{
    public function validateContentCreation(array $data): ValidationResult { /* Custom validation */ }
    public function validateContentUpdate(array $data): ValidationResult { /* Custom validation */ }
}
```

## Design Patterns Used

### 1. Repository Pattern
Abstracts data access logic and provides a uniform interface for different storage backends.

### 2. Factory Pattern
- `RepositoryFactory` for creating repository instances
- Static factory methods on domain objects (`ContentItem::create()`)

### 3. Strategy Pattern
- Block sanitizers and validators are strategies for handling different block types
- Validation adapters are strategies for different validation libraries

### 4. Registry Pattern
- `BlockSanitizerManager` and `BlockValidatorManager` act as registries for block-specific components

### 5. Adapter Pattern
- `SymfonyValidatorAdapter` adapts Symfony Validator to the library's validation interface

### 6. Value Object Pattern
- `ValidationResult`, `ContentCreationRequest`, and `BlockData` are value objects

### 7. Aggregate Pattern
- `ContentItem` is an aggregate root that manages its blocks and enforces consistency

## Error Handling Strategy

### Exception Hierarchy

```
Exception
├── InvalidContentException (Domain errors)
├── RepositoryException (Infrastructure errors)
└── ValidationException (Application errors)
```

### Error Propagation

1. **Domain Layer**: Throws `InvalidContentException` for business rule violations
2. **Application Layer**: Returns `ValidationResult` for validation errors
3. **Infrastructure Layer**: Throws `RepositoryException` for persistence errors

### Transaction Safety

All repository operations use database transactions to ensure ACID properties:

```php
try {
    $this->pdo->beginTransaction();
    // Perform operations
    $this->pdo->commit();
} catch (Exception $e) {
    $this->pdo->rollBack();
    throw RepositoryException::transactionFailure($e->getMessage());
}
```

## Performance Considerations

### 1. Database Optimization
- Proper indexing on frequently queried columns
- Foreign key constraints for referential integrity
- Prepared statements for SQL injection prevention

### 2. Memory Management
- Immutable objects prevent memory leaks from shared references
- Lazy loading could be implemented for large content sets

### 3. Validation Efficiency
- Sanitization before validation reduces validation overhead
- Block-specific validators only process relevant data

## Security Considerations

### 1. Input Sanitization
- All user input is sanitized before validation
- Control characters and malicious content are removed
- Line endings are normalized

### 2. SQL Injection Prevention
- All database queries use prepared statements
- No dynamic SQL construction with user input

### 3. Type Safety
- Strict typing prevents type confusion attacks
- Validation ensures data conforms to expected formats

## Testing Strategy

### 1. Unit Tests
- Test individual components in isolation
- Mock dependencies using interfaces
- Focus on business logic and edge cases

### 2. Integration Tests
- Test component interactions
- Use in-memory database for fast execution
- Verify complete workflows

### 3. End-to-End Tests
- Test complete user scenarios
- Validate entire pipeline from input to persistence
- Performance and scalability testing

This architecture provides a solid foundation that is maintainable, extensible, and testable while following established design patterns and best practices.
