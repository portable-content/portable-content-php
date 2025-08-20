# Phase 1A Implementation Tasks

## Overview
Break down Phase 1A (Markdown MVP) into the smallest possible incremental steps. Each task should be completable in 1-2 hours and have clear acceptance criteria.

## Task Dependencies

```
1. Project Setup (includes Testing Setup)
   ↓
2. Basic Data Classes
   ↓
3. Database Schema
   ↓
4. Repository Pattern
   ↓
5. Validation
   ↓
6. Integration Testing
```

---

## Task 1: Project Setup (includes Testing Infrastructure)
**Estimated Time:** 1.5-2.5 hours
**Dependencies:** None

### Acceptance Criteria:
- [ ] GitHub repository created with descriptive name
- [ ] Basic PHP project structure established
- [ ] Composer initialized with basic dependencies
- [ ] PHPUnit configured with proper test structure
- [ ] Base TestCase class with testing utilities
- [ ] Example tests run successfully
- [ ] README.md with project description
- [ ] .gitignore configured for PHP projects and testing
- [ ] Basic directory structure created

### Implementation Steps:
1. Create GitHub repo (name: `portable-content-php` or similar)
2. Initialize composer project: `composer init`
3. Create directory structure:
   ```
   src/
   tests/
   storage/
   migrations/
   .gitignore
   README.md
   composer.json
   ```
4. Add basic dependencies to composer.json:
   ```json
   {
     "require": {
       "php": "^8.2",
       "ramsey/uuid": "^4.7"
     },
     "require-dev": {
       "phpunit/phpunit": "^10.0"
     }
   }
   ```

### Validation:
- `composer install` runs without errors
- `composer test` runs successfully
- Directory structure is clean and logical
- Repository is accessible and properly configured
- Testing infrastructure is ready for development

---

## Task 2: Basic Data Classes
**Estimated Time:** 1-2 hours  
**Dependencies:** Task 1 (Project Setup)

### Acceptance Criteria:
- [ ] ContentItem class with basic properties
- [ ] MarkdownBlock class with source property
- [ ] Classes can be instantiated and used
- [ ] Basic factory methods work
- [ ] Classes are properly namespaced

### Implementation Steps:
1. Create `src/ContentItem.php`:
   ```php
   <?php
   namespace PortableContent;
   
   final class ContentItem {
       public function __construct(
           public string $id,
           public string $type,
           public ?string $title = null,
           public ?string $summary = null,
           public array $blocks = [],
           public ?\DateTimeImmutable $createdAt = null,
           public ?\DateTimeImmutable $updatedAt = null,
       ) {}
       
       public static function create(string $type, ?string $title = null): self {
           // Implementation
       }
   }
   ```

2. Create `src/MarkdownBlock.php`:
   ```php
   <?php
   namespace PortableContent;
   
   final class MarkdownBlock {
       public function __construct(
           public string $id,
           public string $source,
           public ?\DateTimeImmutable $createdAt = null,
       ) {}
       
       public static function create(string $source): self {
           // Implementation
       }
   }
   ```

3. Add autoloading to composer.json
4. Create simple test script to verify classes work

### Validation:
- Classes can be instantiated
- Factory methods create valid objects
- Properties are accessible
- No PHP syntax errors

---

## Task 3: Database Schema & Migration
**Estimated Time:** 1 hour  
**Dependencies:** Task 2 (Basic Data Classes)

### Acceptance Criteria:
- [ ] SQLite database schema defined
- [ ] Migration script creates tables correctly
- [ ] Database can be initialized from scratch
- [ ] Schema supports the data classes

### Implementation Steps:
1. Create `migrations/001_create_tables.sql`:
   ```sql
   CREATE TABLE content_items (
       id TEXT PRIMARY KEY,
       type TEXT NOT NULL DEFAULT 'note',
       title TEXT,
       summary TEXT,
       created_at TEXT NOT NULL,
       updated_at TEXT NOT NULL
   );
   
   CREATE TABLE markdown_blocks (
       id TEXT PRIMARY KEY,
       content_id TEXT NOT NULL,
       source TEXT NOT NULL,
       created_at TEXT NOT NULL,
       FOREIGN KEY (content_id) REFERENCES content_items(id) ON DELETE CASCADE
   );
   
   CREATE INDEX IF NOT EXISTS idx_content_created ON content_items(created_at);
   CREATE INDEX IF NOT EXISTS idx_blocks_content ON markdown_blocks(content_id);
   ```

2. Create `src/Database.php` with initialization helper:
   ```php
   <?php
   namespace PortableContent;
   
   class Database {
       public static function initialize(string $path): \PDO {
           // Create database and run migrations
       }
   }
   ```

3. Create simple CLI script to initialize database

### Validation:
- Database file is created
- Tables exist with correct schema
- Foreign key constraints work
- Indexes are created

---

## Task 4: Repository Pattern
**Estimated Time:** 2-3 hours  
**Dependencies:** Task 3 (Database Schema)

### Acceptance Criteria:
- [ ] ContentRepositoryInterface defined
- [ ] SQLite implementation of repository
- [ ] Basic CRUD operations work (save, findById, delete)
- [ ] Transactions handle multiple blocks correctly
- [ ] Repository can be instantiated and used

### Implementation Steps:
1. Create `src/ContentRepositoryInterface.php`:
   ```php
   <?php
   namespace PortableContent;
   
   interface ContentRepositoryInterface {
       public function save(ContentItem $content): void;
       public function findById(string $id): ?ContentItem;
       public function findAll(int $limit = 20, int $offset = 0): array;
       public function delete(string $id): void;
   }
   ```

2. Create `src/ContentRepository.php` with SQLite implementation
3. Implement save() method with transaction handling
4. Implement findById() with block loading
5. Implement findAll() with pagination
6. Implement delete() with cascade

### Validation:
- Can save ContentItem with MarkdownBlocks
- Can retrieve saved content by ID
- Can list all content with pagination
- Can delete content and blocks
- Transactions work correctly

---

## Task 5: Input Validation
**Estimated Time:** 1-2 hours
**Dependencies:** Task 4 (Repository Pattern)

### Acceptance Criteria:
- [ ] ContentValidator class validates input data
- [ ] Validates required fields
- [ ] Validates field lengths and formats
- [ ] Returns clear error messages
- [ ] Handles edge cases gracefully

### Implementation Steps:
1. Create `src/ContentValidator.php`:
   ```php
   <?php
   namespace PortableContent;

   class ContentValidator {
       public function validateCreateRequest(array $data): array {
           // Return array of error messages
       }

       private function validateContentFields(array $data): array {
           // Validate content-level fields
       }

       private function validateBlocks(array $blocks): array {
           // Validate block data
       }
   }
   ```

2. Add validation rules:
   - Required fields (type, blocks)
   - Length limits (title, summary, markdown source)
   - Format validation (block structure)

3. Create comprehensive test cases

### Validation:
- Valid data passes validation
- Invalid data returns appropriate errors
- Error messages are clear and helpful
- Edge cases are handled

---

## Task 6: Integration Testing
**Estimated Time:** 2-3 hours
**Dependencies:** Task 5 (Input Validation)

### Acceptance Criteria:
- [ ] End-to-end test creates, saves, and retrieves content
- [ ] Test covers full workflow with validation
- [ ] Test verifies data integrity
- [ ] Test handles error cases
- [ ] All tests pass consistently

### Implementation Steps:
1. Create `tests/Unit/ContentItemTest.php`
2. Create `tests/Unit/MarkdownBlockTest.php`
3. Create `tests/Unit/ContentRepositoryTest.php`
4. Create `tests/Unit/ContentValidatorTest.php`
5. Create `tests/Integration/ContentWorkflowTest.php`:
   ```php
   public function testCompleteContentWorkflow(): void {
       // 1. Create content with validation
       // 2. Save to database
       // 3. Retrieve and verify
       // 4. Update and verify
       // 5. Delete and verify
   }
   ```

### Validation:
- All unit tests pass
- Integration test covers complete workflow
- Tests are fast and reliable
- Code coverage is reasonable (>80%)
- Tests catch regressions

---

## Completion Criteria for Phase 1A

### Functional Requirements:
- [ ] Can create ContentItem with MarkdownBlocks
- [ ] Can save content to SQLite database
- [ ] Can retrieve content by ID
- [ ] Can list all content with pagination
- [ ] Can delete content
- [ ] Input validation works correctly

### Technical Requirements:
- [ ] Clean, well-structured PHP code
- [ ] Comprehensive test suite
- [ ] Database schema is correct
- [ ] Error handling is robust
- [ ] Code follows PSR standards

### Documentation:
- [ ] README explains how to set up and use
- [ ] Code is well-commented
- [ ] API is documented
- [ ] Examples are provided

### Ready for Phase 1B:
- [ ] Solid foundation for adding GraphQL API
- [ ] Data model is proven and tested
- [ ] Storage layer is reliable
- [ ] Validation is comprehensive

---

## Notes

- Each task should be completed and tested before moving to the next
- If a task takes longer than estimated, break it down further
- Run tests after each task to ensure nothing breaks
- Commit code after each completed task
- Document any decisions or trade-offs made during implementation
