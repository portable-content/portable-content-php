# Portable Content PHP Documentation

Welcome to the comprehensive documentation for the Portable Content PHP library.

## Documentation Index

### 🚀 Getting Started
- **[Getting Started Guide](getting-started.md)** - Complete setup and basic usage
  - Installation and requirements
  - Database setup
  - Basic content creation
  - Repository usage
  - Validation examples
  - Error handling

### 📚 Core Documentation
- **[API Reference](api-reference.md)** - Detailed API documentation
  - Complete class reference
  - Method signatures and parameters
  - Return types and exceptions
  - Usage examples for all methods

- **[Validation System](validation.md)** - Input validation and sanitization
  - Sanitization pipeline
  - Validation rules and constraints
  - Error handling and messages
  - Custom validators and sanitizers

- **[Repository Pattern](repository.md)** - Data persistence and retrieval
  - Repository interface and implementations
  - CRUD operations
  - Transaction management
  - Performance considerations

### 🏗️ Advanced Topics
- **[Architecture Overview](architecture.md)** - System design and components
  - Design principles and patterns
  - Component architecture
  - Data flow and processing
  - Extension points

- **[Examples](examples.md)** - Common usage patterns and recipes
  - Practical examples
  - API endpoint patterns
  - Error handling strategies
  - Testing approaches

## Quick Reference

### Core Classes
- **ContentItem** - Main content aggregate with metadata and blocks
- **MarkdownBlock** - Markdown content block implementation
- **ContentValidationService** - Main validation orchestrator
- **RepositoryFactory** - Factory for creating repository instances

### Key Interfaces
- **ContentRepositoryInterface** - Contract for data persistence
- **BlockInterface** - Contract for content blocks
- **ContentValidatorInterface** - Contract for validation
- **SanitizerInterface** - Contract for input sanitization

### Essential Patterns
```php
// Content creation
$content = ContentItem::create('note', 'Title', 'Summary', [$block]);

// Validation
$result = $validationService->validateContentCreation($rawData);

// Repository operations
$repository->save($content);
$retrieved = $repository->findById($content->id);
```

## Documentation Standards

This documentation follows these principles:

- **Practical Examples** - Every concept includes working code examples
- **Complete Coverage** - All public APIs are documented
- **Progressive Complexity** - From basic usage to advanced patterns
- **Error Scenarios** - Common errors and their solutions
- **Best Practices** - Recommended approaches and patterns

## For Different Audiences

### New Developers
Start with [Getting Started Guide](getting-started.md) for step-by-step setup and basic usage.

### Experienced Developers
Jump to [API Reference](api-reference.md) for complete method documentation or [Examples](examples.md) for advanced patterns.

### System Architects
Review [Architecture Overview](architecture.md) for design decisions and system structure.

### AI/LLM Developers
See [../llms.txt](../llms.txt) for structured information optimized for Large Language Models.

## Contributing to Documentation

When contributing to documentation:

1. **Keep Examples Working** - All code examples should be tested and functional
2. **Update Cross-References** - Maintain links between related documentation
3. **Follow Conventions** - Use consistent formatting and structure
4. **Include Context** - Explain why, not just how
5. **Test Instructions** - Verify setup and usage instructions work

## Feedback

If you find issues with the documentation or have suggestions for improvement, please:

1. Check existing documentation for answers
2. Review the [Examples](examples.md) for similar use cases
3. Open an issue with specific feedback
4. Contribute improvements via pull request

The documentation is a living resource that grows with the library and community needs.
