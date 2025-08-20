<?php

declare(strict_types=1);

namespace PortableContent\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversNothing;
use PortableContent\Block\MarkdownBlock;
use PortableContent\ContentItem;

/**
 * @internal
 */
#[CoversNothing]
final class ContentSystemIntegrationTest extends IntegrationTestCase
{
    public function testContentSystemEndToEndWorkflow(): void
    {
        // Simulate a real-world scenario: creating a blog post with multiple content blocks

        // Step 1: Create various content blocks (simulating user input)
        $headerBlock = MarkdownBlock::create('# Building a PHP Content Management System');
        $introBlock = MarkdownBlock::create('In this article, we will explore how to build a flexible content management system using PHP.');
        $codeBlock = MarkdownBlock::create("```php\n\$content = ContentItem::create('article', 'My Article');\n```");
        $conclusionBlock = MarkdownBlock::create('## Conclusion\n\nThis approach provides flexibility and maintainability.');

        // Step 2: Create the main content item (simulating CMS content creation)
        $article = ContentItem::create(
            'article',
            'Building a PHP Content Management System',
            'A comprehensive guide to building flexible content systems'
        );

        // Step 3: Build the content progressively (simulating editor workflow)
        $article = $article
            ->addBlock($headerBlock)
            ->addBlock($introBlock)
            ->addBlock($codeBlock)
            ->addBlock($conclusionBlock)
        ;

        // Step 4: Verify the complete content structure
        $this->assertEquals('article', $article->type);
        $this->assertEquals('Building a PHP Content Management System', $article->title);
        $this->assertCount(4, $article->blocks);

        // Step 5: Simulate content analysis (word count, content types, etc.)
        $totalWords = 0;
        $blockTypes = [];

        foreach ($article->blocks as $block) {
            $totalWords += $block->getWordCount();
            $blockTypes[] = $block->getType();

            // Verify each block has required properties
            $this->assertNotEmpty($block->getId());
            $this->assertInstanceOf(\DateTimeImmutable::class, $block->getCreatedAt());
        }

        $this->assertGreaterThan(20, $totalWords); // Should have substantial content
        $this->assertEquals(['markdown', 'markdown', 'markdown', 'markdown'], $blockTypes);

        // Step 6: Simulate content editing workflow
        $updatedArticle = $article->withTitle('Advanced PHP Content Management System');

        // Verify immutability - original unchanged, new version updated
        $this->assertEquals('Building a PHP Content Management System', $article->title);
        $this->assertEquals('Advanced PHP Content Management System', $updatedArticle->title);
        $this->assertCount(4, $updatedArticle->blocks); // Blocks preserved
    }

    public function testMultipleContentTypesWorkflow(): void
    {
        // Simulate a CMS managing different content types

        // Create a note
        $note = ContentItem::create('note', 'Quick Reminder')
            ->addBlock(MarkdownBlock::create('Remember to update the documentation.'))
        ;

        // Create an article
        $article = ContentItem::create('article', 'Technical Guide')
            ->addBlock(MarkdownBlock::create('# Introduction'))
            ->addBlock(MarkdownBlock::create('This guide covers advanced topics.'))
        ;

        // Create a page
        $page = ContentItem::create('page', 'About Us')
            ->addBlock(MarkdownBlock::create('## Our Mission'))
            ->addBlock(MarkdownBlock::create('We strive to build excellent software.'))
        ;

        // Simulate a content management system handling different types
        $contentItems = [$note, $article, $page];

        // Verify each content type maintains its identity and structure
        $this->assertEquals('note', $contentItems[0]->type);
        $this->assertEquals('article', $contentItems[1]->type);
        $this->assertEquals('page', $contentItems[2]->type);

        // Verify polymorphic behavior across all content
        foreach ($contentItems as $content) {
            $this->assertNotEmpty($content->id);
            $this->assertInstanceOf(\DateTimeImmutable::class, $content->createdAt);
            $this->assertIsArray($content->blocks);

            // Each content item should have at least one block
            $this->assertGreaterThan(0, count($content->blocks));

            // All blocks should implement the interface consistently
            foreach ($content->blocks as $block) {
                $this->assertEquals('markdown', $block->getType());
                $this->assertIsInt($block->getWordCount());
            }
        }

        // Simulate content statistics gathering
        $totalContentItems = count($contentItems);
        $totalBlocks = array_sum(array_map(fn ($content) => count($content->blocks), $contentItems));

        $this->assertEquals(3, $totalContentItems);
        $this->assertEquals(5, $totalBlocks); // 1 + 2 + 2 blocks
    }
}
