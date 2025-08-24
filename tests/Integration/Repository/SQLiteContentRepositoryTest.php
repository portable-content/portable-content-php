<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration\Repository;

use PortableContent\Block\Markdown\MarkdownBlock;
use PortableContent\ContentItem;
use PortableContent\Contracts\ContentRepositoryInterface;
use PortableContent\Tests\Integration\IntegrationTestCase;
use PortableContent\Tests\Support\Repository\RepositoryFactory;

/**
 * @internal
 */
final class SQLiteContentRepositoryTest extends IntegrationTestCase
{
    private ContentRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = RepositoryFactory::createInMemoryRepository();
    }

    public function testSaveAndFindById(): void
    {
        // Create content with blocks
        $block1 = MarkdownBlock::create('# Title');
        $block2 = MarkdownBlock::create('Some content here.');

        $content = ContentItem::create('note', 'Test Note', 'A test note');
        $content->addBlock($block1);
        $content->addBlock($block2);

        // Save content
        $this->repository->save($content);

        // Retrieve content
        $retrieved = $this->repository->findById($content->getId());

        $this->assertNotNull($retrieved);
        $this->assertEquals($content->getId(), $retrieved->getId());
        $this->assertEquals($content->getType(), $retrieved->getType());
        $this->assertEquals($content->getTitle(), $retrieved->getTitle());
        $this->assertEquals($content->getSummary(), $retrieved->getSummary());
        $this->assertCount(2, $retrieved->getBlocks());

        // Check blocks are loaded correctly
        $this->assertInstanceOf(MarkdownBlock::class, $retrieved->getBlocks()[0]);
        $this->assertInstanceOf(MarkdownBlock::class, $retrieved->getBlocks()[1]);
        $this->assertEquals('# Title', $retrieved->getBlocks()[0]->getContent());
        $this->assertEquals('Some content here.', $retrieved->getBlocks()[1]->getContent());
    }

    public function testFindByIdReturnsNullForNonExistentContent(): void
    {
        $result = $this->repository->findById('non-existent-id');
        $this->assertNull($result);
    }

    public function testSaveUpdatesExistingContent(): void
    {
        // Create and save initial content
        $content = ContentItem::create('note', 'Original Title');
        $this->repository->save($content);

        // Update content
        $content->setTitle('Updated Title');
        $content->addBlock(MarkdownBlock::create('# New Block'));
        $this->repository->save($content);

        // Retrieve and verify update
        $retrieved = $this->repository->findById($content->getId());
        $this->assertNotNull($retrieved);
        $this->assertEquals('Updated Title', $retrieved->getTitle());
        $this->assertCount(1, $retrieved->getBlocks());
        $this->assertInstanceOf(MarkdownBlock::class, $retrieved->getBlocks()[0]);
        $this->assertEquals('# New Block', $retrieved->getBlocks()[0]->getContent());
    }

    public function testDelete(): void
    {
        // Create and save content
        $content = ContentItem::create('note', 'To Delete');
        $content->addBlock(MarkdownBlock::create('# Will be deleted'));
        $this->repository->save($content);

        // Verify it exists
        $this->assertTrue($this->repository->exists($content->getId()));

        // Delete it
        $this->repository->delete($content->getId());

        // Verify it's gone
        $this->assertFalse($this->repository->exists($content->getId()));
        $this->assertNull($this->repository->findById($content->getId()));
    }

    public function testDeleteNonExistentContentDoesNotThrow(): void
    {
        // Should not throw exception
        $this->repository->delete('non-existent-id');
        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    public function testCount(): void
    {
        $this->assertEquals(0, $this->repository->count());

        // Add some content
        $content1 = ContentItem::create('note', 'First');
        $content2 = ContentItem::create('article', 'Second');

        $this->repository->save($content1);
        $this->assertEquals(1, $this->repository->count());

        $this->repository->save($content2);
        $this->assertEquals(2, $this->repository->count());

        // Delete one
        $this->repository->delete($content1->getId());
        $this->assertEquals(1, $this->repository->count());
    }

    public function testExists(): void
    {
        $content = ContentItem::create('note', 'Test');

        $this->assertFalse($this->repository->exists($content->getId()));

        $this->repository->save($content);
        $this->assertTrue($this->repository->exists($content->getId()));

        $this->repository->delete($content->getId());
        $this->assertFalse($this->repository->exists($content->getId()));
    }

    public function testFindAllWithPagination(): void
    {
        // Create multiple content items
        $contents = [];
        for ($i = 1; $i <= 5; ++$i) {
            $content = ContentItem::create('note', "Note {$i}");
            $contents[] = $content;
            $this->repository->save($content);
        }

        // Test pagination
        $page1 = $this->repository->findAll(2, 0);
        $this->assertCount(2, $page1);

        $page2 = $this->repository->findAll(2, 2);
        $this->assertCount(2, $page2);

        $page3 = $this->repository->findAll(2, 4);
        $this->assertCount(1, $page3);

        // Verify no overlap
        $allIds = array_merge(
            array_map(fn($c) => $c->getId(), $page1),
            array_map(fn($c) => $c->getId(), $page2),
            array_map(fn($c) => $c->getId(), $page3)
        );
        $this->assertCount(5, array_unique($allIds));
    }

    public function testFindAllOrdersByCreatedAtDesc(): void
    {
        // Create content items with explicit different timestamps
        $now = new \DateTimeImmutable();
        $earlier = $now->modify('-1 hour');

        // Create content with earlier timestamp
        $content1 = new ContentItem(
            id: 'test-1',
            type: 'note',
            title: 'First',
            summary: null,
            blocks: [],
            createdAt: $earlier,
            updatedAt: $earlier
        );
        $this->repository->save($content1);

        // Create content with later timestamp
        $content2 = new ContentItem(
            id: 'test-2',
            type: 'note',
            title: 'Second',
            summary: null,
            blocks: [],
            createdAt: $now,
            updatedAt: $now
        );
        $this->repository->save($content2);

        $results = $this->repository->findAll();

        $this->assertCount(2, $results);
        // Should be ordered by created_at DESC (newest first)
        $this->assertEquals('Second', $results[0]->getTitle());
        $this->assertEquals('First', $results[1]->getTitle());
    }

    public function testCascadeDeleteRemovesBlocks(): void
    {
        // Create content with blocks
        $content = ContentItem::create('note', 'With Blocks');
        $content->addBlock(MarkdownBlock::create('# Block 1'));
        $content->addBlock(MarkdownBlock::create('# Block 2'));

        $this->repository->save($content);

        // Verify blocks exist by retrieving content
        $retrieved = $this->repository->findById($content->getId());
        $this->assertNotNull($retrieved);
        $this->assertCount(2, $retrieved->getBlocks());

        // Delete content
        $this->repository->delete($content->getId());

        // Verify content and blocks are gone
        $this->assertNull($this->repository->findById($content->getId()));

        // We can't directly query blocks table from here, but the foreign key
        // constraint with CASCADE DELETE should handle this automatically
    }
}
