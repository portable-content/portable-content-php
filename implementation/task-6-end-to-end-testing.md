# Task 6: End-to-End Testing - Detailed Steps

## Overview
Create comprehensive end-to-end tests that validate the complete system works together. This includes testing the full workflow from validation through repository operations, ensuring all components integrate properly.

**Estimated Time:** 1.5-2 hours  
**Dependencies:** Task 5 (Input Validation) must be completed

---

## Step 6.1: Plan End-to-End Test Scenarios
**Time:** 10-15 minutes

### Instructions:
Before writing tests, let's define the complete workflows we need to test:

**Core Workflows:**
1. **Complete Content Creation**: Validate → Create → Save → Retrieve
2. **Content Update Workflow**: Retrieve → Validate → Update → Save → Verify
3. **Content Deletion Workflow**: Create → Save → Delete → Verify
4. **Error Handling Workflow**: Invalid input → Validation errors → Graceful handling
5. **Multi-Content Scenarios**: Multiple content items with different types

**Integration Points to Test:**
- Validation Service + Repository
- ContentItem + MarkdownBlock + Database
- Factory patterns + Repository patterns
- Error propagation across layers

**Edge Cases:**
- Large content with many blocks
- Unicode content handling
- Concurrent operations simulation
- Database constraint violations

### Test Strategy:
```php
// End-to-end test structure
1. Setup: Clean database state
2. Action: Perform complete workflow
3. Verification: Check all side effects
4. Cleanup: Reset state for next test
```

### Validation:
- [ ] Test scenarios are comprehensive
- [ ] Integration points are identified
- [ ] Edge cases are planned
- [ ] Test strategy is clear

---

## Step 6.2: Create Complete Workflow Tests
**Time:** 25-30 minutes

### Instructions:
1. Create `tests/Integration/CompleteWorkflowTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PortableContent\Repository\RepositoryFactory;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\ValidationException;
use PortableContent\Validation\ValidationService;

final class CompleteWorkflowTest extends TestCase
{
    private ValidationService $validationService;
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new ValidationService();
        $this->repository = RepositoryFactory::createInMemoryRepository();
    }

    public function testCompleteContentCreationWorkflow(): void
    {
        // 1. Raw input data (as would come from API)
        $inputData = [
            'type' => '  note  ', // Test sanitization
            'title' => 'My First Note',
            'summary' => 'A comprehensive test note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Introduction\n\nThis is the introduction to my note.'
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## Details\n\nHere are the important details:\n\n- Point 1\n- Point 2\n- Point 3'
                ]
            ]
        ];

        // 2. Validate and sanitize input
        $validatedData = $this->validationService->validateContentCreation($inputData);
        $this->assertEquals('note', $validatedData['type']); // Sanitized

        // 3. Create content object from validated data
        $content = $this->validationService->createContentFromValidatedData($validatedData);
        $this->assertNotEmpty($content->id);
        $this->assertEquals('note', $content->type);
        $this->assertEquals('My First Note', $content->title);
        $this->assertCount(2, $content->blocks);

        // 4. Validate the created object
        $this->validationService->validateContentItem($content);

        // 5. Save to repository
        $this->repository->save($content);
        $this->assertEquals(1, $this->repository->count());

        // 6. Retrieve and verify complete round-trip
        $retrieved = $this->repository->findById($content->id);
        $this->assertNotNull($retrieved);
        $this->assertEquals($content->id, $retrieved->id);
        $this->assertEquals($content->title, $retrieved->title);
        $this->assertEquals($content->summary, $retrieved->summary);
        $this->assertCount(2, $retrieved->blocks);
        
        // Verify block content integrity
        $this->assertEquals('# Introduction\n\nThis is the introduction to my note.', $retrieved->blocks[0]->source);
        $this->assertStringContains('Point 1', $retrieved->blocks[1]->source);

        // 7. Verify timestamps are preserved
        $this->assertEquals($content->createdAt->format('c'), $retrieved->createdAt->format('c'));
        $this->assertEquals($content->updatedAt->format('c'), $retrieved->updatedAt->format('c'));
    }

    public function testCompleteContentUpdateWorkflow(): void
    {
        // 1. Create initial content
        $initialData = [
            'type' => 'article',
            'title' => 'Original Article',
            'summary' => 'Original summary',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Original Content\n\nThis is the original content.'
                ]
            ]
        ];

        $validatedData = $this->validationService->validateContentCreation($initialData);
        $originalContent = $this->validationService->createContentFromValidatedData($validatedData);
        $this->repository->save($originalContent);

        // 2. Prepare update data
        $updateData = [
            'title' => 'Updated Article Title',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '# Updated Content\n\nThis content has been updated.'
                ],
                [
                    'kind' => 'markdown',
                    'source' => '## New Section\n\nThis is a completely new section.'
                ]
            ]
        ];

        // 3. Validate update data
        $validatedUpdateData = $this->validationService->validateContentUpdate($updateData);

        // 4. Apply updates to content
        $updatedContent = $this->validationService->updateContentWithValidatedData(
            $originalContent, 
            $validatedUpdateData
        );

        // 5. Validate updated content
        $this->validationService->validateContentItem($updatedContent);

        // 6. Save updated content
        $this->repository->save($updatedContent);
        $this->assertEquals(1, $this->repository->count()); // Still only one item

        // 7. Retrieve and verify updates
        $retrieved = $this->repository->findById($originalContent->id);
        $this->assertEquals('Updated Article Title', $retrieved->title);
        $this->assertEquals('Original summary', $retrieved->summary); // Unchanged
        $this->assertCount(2, $retrieved->blocks); // New block count
        $this->assertStringContains('Updated Content', $retrieved->blocks[0]->source);
        $this->assertStringContains('New Section', $retrieved->blocks[1]->source);

        // 8. Verify timestamps
        $this->assertEquals($originalContent->createdAt->format('c'), $retrieved->createdAt->format('c'));
        $this->assertNotEquals($originalContent->updatedAt->format('c'), $retrieved->updatedAt->format('c'));
    }

    public function testCompleteContentDeletionWorkflow(): void
    {
        // 1. Create multiple content items
        $content1Data = [
            'type' => 'note',
            'title' => 'Note to Keep',
            'blocks' => [['kind' => 'markdown', 'source' => '# Keep this']]
        ];

        $content2Data = [
            'type' => 'note',
            'title' => 'Note to Delete',
            'blocks' => [['kind' => 'markdown', 'source' => '# Delete this']]
        ];

        $validatedData1 = $this->validationService->validateContentCreation($content1Data);
        $validatedData2 = $this->validationService->validateContentCreation($content2Data);

        $content1 = $this->validationService->createContentFromValidatedData($validatedData1);
        $content2 = $this->validationService->createContentFromValidatedData($validatedData2);

        $this->repository->save($content1);
        $this->repository->save($content2);
        $this->assertEquals(2, $this->repository->count());

        // 2. Delete one content item
        $this->repository->delete($content2->id);

        // 3. Verify deletion
        $this->assertEquals(1, $this->repository->count());
        $this->assertNull($this->repository->findById($content2->id));
        $this->assertNotNull($this->repository->findById($content1->id));

        // 4. Verify remaining content is intact
        $remaining = $this->repository->findById($content1->id);
        $this->assertEquals('Note to Keep', $remaining->title);
        $this->assertCount(1, $remaining->blocks);
    }

    public function testErrorHandlingWorkflow(): void
    {
        // 1. Test validation error handling
        $invalidData = [
            'type' => '', // Invalid
            'title' => str_repeat('x', 300), // Too long
            'blocks' => [] // Empty
        ];

        try {
            $this->validationService->validateContentCreation($invalidData);
            $this->fail('Expected ValidationException');
        } catch (ValidationException $e) {
            $this->assertGreaterThan(0, count($e->getAllMessages()));
            $this->assertTrue($e->hasFieldErrors('type'));
            $this->assertTrue($e->hasFieldErrors('title'));
            $this->assertTrue($e->hasFieldErrors('blocks'));
        }

        // 2. Test repository error handling with non-existent content
        $nonExistent = $this->repository->findById('non-existent-id');
        $this->assertNull($nonExistent);

        // 3. Test graceful handling of delete non-existent
        $this->repository->delete('non-existent-id'); // Should not throw
        $this->assertTrue(true);

        // 4. Verify repository state is still clean
        $this->assertEquals(0, $this->repository->count());
    }

    public function testMultiContentScenarios(): void
    {
        $contentTypes = ['note', 'article', 'draft'];
        $createdContent = [];

        // 1. Create multiple content items of different types
        foreach ($contentTypes as $index => $type) {
            $data = [
                'type' => $type,
                'title' => ucfirst($type) . ' ' . ($index + 1),
                'summary' => "This is a {$type} for testing",
                'blocks' => [
                    [
                        'kind' => 'markdown',
                        'source' => "# {$type} Content\n\nThis is {$type} content for testing."
                    ]
                ]
            ];

            $validatedData = $this->validationService->validateContentCreation($data);
            $content = $this->validationService->createContentFromValidatedData($validatedData);
            $this->repository->save($content);
            $createdContent[] = $content;
        }

        // 2. Verify all content was saved
        $this->assertEquals(3, $this->repository->count());

        // 3. Test pagination
        $page1 = $this->repository->findAll(2, 0);
        $page2 = $this->repository->findAll(2, 2);
        
        $this->assertCount(2, $page1);
        $this->assertCount(1, $page2);

        // 4. Verify content integrity across all items
        foreach ($createdContent as $original) {
            $retrieved = $this->repository->findById($original->id);
            $this->assertNotNull($retrieved);
            $this->assertEquals($original->type, $retrieved->type);
            $this->assertEquals($original->title, $retrieved->title);
            $this->assertCount(1, $retrieved->blocks);
        }

        // 5. Test bulk operations
        $allContent = $this->repository->findAll(10, 0);
        $this->assertCount(3, $allContent);

        // Verify ordering (should be by created_at DESC)
        $titles = array_map(fn($c) => $c->title, $allContent);
        $this->assertEquals(['Draft 3', 'Article 2', 'Note 1'], $titles);
    }
}
```

### Validation:
- [ ] Complete workflow tests are created
- [ ] All major workflows are covered
- [ ] Error handling is tested
- [ ] Multi-content scenarios are tested
- [ ] Tests verify end-to-end functionality

---

## Step 6.3: Create Performance and Edge Case Tests
**Time:** 20-25 minutes

### Instructions:
1. Create `tests/Integration/EdgeCaseTest.php`:

```php
<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PortableContent\Repository\RepositoryFactory;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\ValidationService;

final class EdgeCaseTest extends TestCase
{
    private ValidationService $validationService;
    private $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validationService = new ValidationService();
        $this->repository = RepositoryFactory::createInMemoryRepository();
    }

    public function testLargeContentWithManyBlocks(): void
    {
        // Create content with maximum allowed blocks
        $blocks = [];
        for ($i = 1; $i <= 10; $i++) {
            $blocks[] = [
                'kind' => 'markdown',
                'source' => "# Section {$i}\n\n" . str_repeat("Content for section {$i}. ", 100)
            ];
        }

        $data = [
            'type' => 'article',
            'title' => 'Large Article with Many Blocks',
            'summary' => 'This article tests the system with maximum blocks',
            'blocks' => $blocks
        ];

        $validatedData = $this->validationService->validateContentCreation($data);
        $content = $this->validationService->createContentFromValidatedData($validatedData);
        
        // Verify content creation
        $this->assertCount(10, $content->blocks);
        
        // Test save and retrieve
        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);
        
        $this->assertNotNull($retrieved);
        $this->assertCount(10, $retrieved->blocks);
        
        // Verify content integrity
        for ($i = 0; $i < 10; $i++) {
            $this->assertStringContains("Section " . ($i + 1), $retrieved->blocks[$i]->source);
        }
    }

    public function testUnicodeContentHandling(): void
    {
        $unicodeData = [
            'type' => 'note',
            'title' => '测试笔记 📝 Тест العربية',
            'summary' => 'Unicode test: 🚀 ñáéíóú αβγδε 中文测试',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => "# Unicode Test 🌍\n\n" .
                               "Chinese: 你好世界\n" .
                               "Arabic: مرحبا بالعالم\n" .
                               "Russian: Привет мир\n" .
                               "Emoji: 🎉🎊🎈🎁\n" .
                               "Math: ∑∞∫∂∇"
                ]
            ]
        ];

        $validatedData = $this->validationService->validateContentCreation($unicodeData);
        $content = $this->validationService->createContentFromValidatedData($validatedData);
        
        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);
        
        $this->assertNotNull($retrieved);
        $this->assertEquals('测试笔记 📝 Тест العربية', $retrieved->title);
        $this->assertStringContains('你好世界', $retrieved->blocks[0]->source);
        $this->assertStringContains('🎉🎊🎈🎁', $retrieved->blocks[0]->source);
    }

    public function testConcurrentOperationsSimulation(): void
    {
        // Simulate concurrent saves (in real app, this would be different processes)
        $contentData = [];
        for ($i = 1; $i <= 5; $i++) {
            $contentData[] = [
                'type' => 'note',
                'title' => "Concurrent Note {$i}",
                'blocks' => [
                    [
                        'kind' => 'markdown',
                        'source' => "# Note {$i}\n\nContent for note {$i}"
                    ]
                ]
            ];
        }

        $savedContent = [];
        
        // "Concurrent" saves
        foreach ($contentData as $data) {
            $validatedData = $this->validationService->validateContentCreation($data);
            $content = $this->validationService->createContentFromValidatedData($validatedData);
            $this->repository->save($content);
            $savedContent[] = $content;
        }

        // Verify all content exists
        $this->assertEquals(5, $this->repository->count());

        // Verify each content item individually
        foreach ($savedContent as $original) {
            $retrieved = $this->repository->findById($original->id);
            $this->assertNotNull($retrieved);
            $this->assertEquals($original->title, $retrieved->title);
        }

        // Test concurrent updates
        foreach ($savedContent as $index => $content) {
            $updated = $content->withTitle("Updated Concurrent Note " . ($index + 1));
            $this->repository->save($updated);
        }

        // Verify all updates
        foreach ($savedContent as $index => $original) {
            $retrieved = $this->repository->findById($original->id);
            $this->assertEquals("Updated Concurrent Note " . ($index + 1), $retrieved->title);
        }
    }

    public function testEmptyAndMinimalContent(): void
    {
        // Test minimal valid content
        $minimalData = [
            'type' => 'note',
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => '#'  // Minimal markdown
                ]
            ]
        ];

        $validatedData = $this->validationService->validateContentCreation($minimalData);
        $content = $this->validationService->createContentFromValidatedData($validatedData);
        
        $this->assertNull($content->title);
        $this->assertNull($content->summary);
        $this->assertCount(1, $content->blocks);
        
        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);
        
        $this->assertNotNull($retrieved);
        $this->assertNull($retrieved->title);
        $this->assertEquals('#', $retrieved->blocks[0]->source);
    }

    public function testContentWithSpecialCharacters(): void
    {
        $specialData = [
            'type' => 'note',
            'title' => 'Special "Characters" & <Tags>',
            'summary' => "Line 1\nLine 2\tTabbed\r\nWindows line ending",
            'blocks' => [
                [
                    'kind' => 'markdown',
                    'source' => "# Special Characters Test\n\n" .
                               "Quotes: \"double\" 'single'\n" .
                               "Symbols: &amp; &lt; &gt;\n" .
                               "Code: `console.log('hello');`\n" .
                               "SQL: SELECT * FROM table WHERE id = 'test';\n" .
                               "JSON: {\"key\": \"value\", \"number\": 123}"
                ]
            ]
        ];

        $validatedData = $this->validationService->validateContentCreation($specialData);
        $content = $this->validationService->createContentFromValidatedData($validatedData);
        
        $this->repository->save($content);
        $retrieved = $this->repository->findById($content->id);
        
        $this->assertNotNull($retrieved);
        $this->assertEquals('Special "Characters" & <Tags>', $retrieved->title);
        $this->assertStringContains('console.log', $retrieved->blocks[0]->source);
        $this->assertStringContains('"key": "value"', $retrieved->blocks[0]->source);
    }

    public function testRepositoryLimitsAndPagination(): void
    {
        // Create more content than typical page size
        for ($i = 1; $i <= 25; $i++) {
            $data = [
                'type' => 'note',
                'title' => sprintf('Note %02d', $i),
                'blocks' => [
                    [
                        'kind' => 'markdown',
                        'source' => "# Note {$i}\n\nContent for note {$i}"
                    ]
                ]
            ];

            $validatedData = $this->validationService->validateContentCreation($data);
            $content = $this->validationService->createContentFromValidatedData($validatedData);
            $this->repository->save($content);
        }

        $this->assertEquals(25, $this->repository->count());

        // Test various pagination scenarios
        $page1 = $this->repository->findAll(10, 0);
        $page2 = $this->repository->findAll(10, 10);
        $page3 = $this->repository->findAll(10, 20);

        $this->assertCount(10, $page1);
        $this->assertCount(10, $page2);
        $this->assertCount(5, $page3);

        // Test edge cases
        $emptyPage = $this->repository->findAll(10, 100);
        $this->assertCount(0, $emptyPage);

        $singleItem = $this->repository->findAll(1, 0);
        $this->assertCount(1, $singleItem);
    }
}
```

### Validation:
- [ ] Edge case tests are created
- [ ] Large content scenarios are tested
- [ ] Unicode handling is verified
- [ ] Concurrent operations are simulated
- [ ] Special characters are handled correctly
- [ ] Pagination edge cases are tested

---

## Step 6.4: Run Complete Test Suite
**Time:** 10-15 minutes

### Instructions:
1. Run all tests to ensure everything works together:

```bash
# Run all tests
composer test

# Run with verbose output
./vendor/bin/phpunit --verbose

# Run only integration tests
./vendor/bin/phpunit --testsuite=Integration

# Run with coverage
composer test-coverage
```

2. Verify test results and coverage:

```bash
# Check coverage report
open coverage/index.html  # On macOS
# or
xdg-open coverage/index.html  # On Linux
```

### Expected Results:
- All unit tests pass (from previous tasks)
- All integration tests pass
- No errors or warnings
- Good test coverage (>80%)
- Performance is acceptable

### Validation:
- [ ] All tests pass successfully
- [ ] No errors or warnings in output
- [ ] Coverage report shows good coverage
- [ ] Integration tests verify complete workflows
- [ ] Edge cases are handled properly

---

## Step 6.5: Create Test Documentation
**Time:** 10-15 minutes

### Instructions:
1. Update README.md to add testing section:

Add this after the "Validation" section:

```markdown
## Testing

### Running Tests

```bash
# Run all tests
composer test

# Run specific test suites
./vendor/bin/phpunit --testsuite=Unit
./vendor/bin/phpunit --testsuite=Integration

# Run with coverage
composer test-coverage

# Run specific test file
./vendor/bin/phpunit tests/Unit/ContentItemTest.php

# Run with verbose output
./vendor/bin/phpunit --verbose
```

### Test Structure

```
tests/
├── Unit/                           # Unit tests for individual classes
│   ├── ContentItemTest.php         # ContentItem class tests
│   ├── MarkdownBlockTest.php       # MarkdownBlock class tests
│   ├── Database/
│   │   └── DatabaseTest.php        # Database helper tests
│   ├── Repository/
│   │   ├── SQLiteContentRepositoryTest.php
│   │   └── RepositoryFactoryTest.php
│   └── Validation/
│       ├── ContentValidatorTest.php
│       └── ValidationServiceTest.php
└── Integration/                    # Integration and end-to-end tests
    ├── CompleteWorkflowTest.php    # Full workflow testing
    ├── ContentWorkflowTest.php     # Repository integration tests
    └── EdgeCaseTest.php           # Edge cases and performance tests
```

### Test Coverage

The test suite covers:

- **Unit Tests**: Individual class functionality and edge cases
- **Integration Tests**: Component interaction and complete workflows
- **Edge Cases**: Large content, Unicode, special characters
- **Error Handling**: Validation errors and repository exceptions
- **Performance**: Large datasets and pagination

Target coverage: >80% for production readiness.
```

### Validation:
- [ ] README.md includes comprehensive testing documentation
- [ ] Test structure is clearly explained
- [ ] Running instructions are provided
- [ ] Coverage expectations are set

---

## Step 6.6: Commit the Changes
**Time:** 5 minutes

### Instructions:
1. Stage all changes:
```bash
git add .
```

2. Commit with descriptive message:
```bash
git commit -m "Implement comprehensive end-to-end testing suite

- Created complete workflow tests covering validation to repository
- Added edge case tests for large content and Unicode handling
- Implemented performance tests with pagination scenarios
- Added concurrent operation simulation tests
- Created comprehensive test documentation
- Verified all components work together correctly

Phase 1A MVP is now fully tested and ready for production use."
```

3. Push to GitHub:
```bash
git push origin main
```

### Validation:
- [ ] All files are committed
- [ ] Commit message describes the testing implementation
- [ ] Changes are pushed to GitHub
- [ ] Phase 1A is complete and tested

---

## Completion Checklist

### End-to-End Testing:
- [ ] Complete workflow tests cover validation → repository
- [ ] Content creation, update, and deletion workflows tested
- [ ] Error handling workflows verified
- [ ] Multi-content scenarios tested

### Edge Case Testing:
- [ ] Large content with maximum blocks tested
- [ ] Unicode content handling verified
- [ ] Special characters handled correctly
- [ ] Concurrent operations simulated
- [ ] Pagination edge cases covered

### Test Infrastructure:
- [ ] All tests pass consistently
- [ ] Good test coverage achieved (>80%)
- [ ] Test documentation is comprehensive
- [ ] Performance is acceptable

### Phase 1A Completion:
- [ ] All 6 tasks completed successfully
- [ ] Complete system works end-to-end
- [ ] Comprehensive test coverage
- [ ] Documentation is complete
- [ ] Ready for Phase 1B (GraphQL API)

---

## Next Steps

With Task 6 complete, **Phase 1A is finished!** You now have:

✅ **Complete Content System**: ContentItem and MarkdownBlock classes  
✅ **Database Layer**: SQLite with migrations and helpers  
✅ **Repository Pattern**: Clean data access with transactions  
✅ **Input Validation**: Comprehensive validation with clear errors  
✅ **Testing Infrastructure**: Unit and integration tests  
✅ **End-to-End Verification**: Complete workflows tested  

**Phase 1A Deliverables:**
- Solid foundation for content storage and retrieval
- Well-tested, production-ready code
- Clear documentation and examples
- Database-agnostic design
- Comprehensive error handling

You're now ready to move on to **Phase 1B: GraphQL API** where you'll build a GraphQL API on top of this solid foundation!

## Troubleshooting

### Common Issues:

**Tests failing:**
- Ensure all previous tasks are completed
- Check that database migrations run correctly
- Verify all dependencies are installed

**Performance issues:**
- Monitor test execution time
- Consider optimizing database queries if needed
- Use in-memory database for faster tests

**Coverage issues:**
- Review uncovered code paths
- Add tests for edge cases
- Ensure all public methods are tested
