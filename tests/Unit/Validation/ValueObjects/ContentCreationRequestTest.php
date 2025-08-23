<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation\ValueObjects;

use PortableContent\Tests\TestCase;
use PortableContent\Validation\ValueObjects\BlockData;
use PortableContent\Validation\ValueObjects\ContentCreationRequest;

/**
 * @internal
 */
final class ContentCreationRequestTest extends TestCase
{
    public function testConstructorSetsPropertiesCorrectly(): void
    {
        $blocks = [
            BlockData::markdown('# Title'),
            BlockData::markdown('Content here'),
        ];

        $request = new ContentCreationRequest('note', 'Test Title', 'Test Summary', $blocks);

        $this->assertEquals('note', $request->type);
        $this->assertEquals('Test Title', $request->title);
        $this->assertEquals('Test Summary', $request->summary);
        $this->assertEquals($blocks, $request->blocks);
    }

    public function testFromArrayCreatesCorrectInstance(): void
    {
        $data = [
            'type' => 'article',
            'title' => 'My Article',
            'summary' => 'Article summary',
            'blocks' => [
                ['kind' => 'markdown', 'source' => '# Heading'],
                ['kind' => 'markdown', 'source' => 'Paragraph content'],
            ],
        ];

        $request = ContentCreationRequest::fromArray($data);

        $this->assertEquals('article', $request->type);
        $this->assertEquals('My Article', $request->title);
        $this->assertEquals('Article summary', $request->summary);
        $this->assertCount(2, $request->blocks);
        $this->assertEquals('markdown', $request->blocks[0]->kind);
        $this->assertEquals('# Heading', $request->blocks[0]->source);
    }

    public function testFromArrayWithMissingOptionalFields(): void
    {
        $data = [
            'type' => 'note',
            'blocks' => [
                ['kind' => 'markdown', 'source' => 'Content'],
            ],
        ];

        $request = ContentCreationRequest::fromArray($data);

        $this->assertEquals('note', $request->type);
        $this->assertNull($request->title);
        $this->assertNull($request->summary);
        $this->assertCount(1, $request->blocks);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $blocks = [BlockData::markdown('# Test')];
        $request = new ContentCreationRequest('note', 'Title', 'Summary', $blocks);

        $array = $request->toArray();

        $expected = [
            'type' => 'note',
            'title' => 'Title',
            'summary' => 'Summary',
            'blocks' => [
                ['kind' => 'markdown', 'source' => '# Test'],
            ],
        ];

        $this->assertEquals($expected, $array);
    }

    public function testToArrayWithNullValues(): void
    {
        $blocks = [BlockData::markdown('Content')];
        $request = new ContentCreationRequest('note', null, null, $blocks);

        $array = $request->toArray();

        $this->assertNull($array['title']);
        $this->assertNull($array['summary']);
        $this->assertEquals('note', $array['type']);
    }

    public function testGetBlockCountReturnsCorrectNumber(): void
    {
        $blocks = [
            BlockData::markdown('Block 1'),
            BlockData::markdown('Block 2'),
            BlockData::markdown('Block 3'),
        ];

        $request = new ContentCreationRequest('note', null, null, $blocks);

        $this->assertEquals(3, $request->getBlockCount());
    }

    public function testGetBlockCountWithEmptyBlocks(): void
    {
        $request = new ContentCreationRequest('note', null, null, []);

        $this->assertEquals(0, $request->getBlockCount());
    }

    public function testHasTitleReturnsTrueForNonEmptyTitle(): void
    {
        $request = new ContentCreationRequest('note', 'My Title', null, []);

        $this->assertTrue($request->hasTitle());
    }

    public function testHasTitleReturnsFalseForNullTitle(): void
    {
        $request = new ContentCreationRequest('note', null, null, []);

        $this->assertFalse($request->hasTitle());
    }

    public function testHasTitleReturnsFalseForEmptyTitle(): void
    {
        $request = new ContentCreationRequest('note', '   ', null, []);

        $this->assertFalse($request->hasTitle());
    }

    public function testHasSummaryReturnsTrueForNonEmptySummary(): void
    {
        $request = new ContentCreationRequest('note', null, 'My Summary', []);

        $this->assertTrue($request->hasSummary());
    }

    public function testHasSummaryReturnsFalseForNullSummary(): void
    {
        $request = new ContentCreationRequest('note', null, null, []);

        $this->assertFalse($request->hasSummary());
    }

    public function testHasSummaryReturnsFalseForEmptySummary(): void
    {
        $request = new ContentCreationRequest('note', null, '   ', []);

        $this->assertFalse($request->hasSummary());
    }

    public function testGetBlocksByKindReturnsMatchingBlocks(): void
    {
        $blocks = [
            BlockData::markdown('Markdown 1'),
            new BlockData('html', '<p>HTML content</p>'),
            BlockData::markdown('Markdown 2'),
        ];

        $request = new ContentCreationRequest('note', null, null, $blocks);

        $markdownBlocks = $request->getBlocksByKind('markdown');
        $this->assertCount(2, $markdownBlocks);
        $this->assertEquals('Markdown 1', $markdownBlocks[0]->source);
        $this->assertEquals('Markdown 2', $markdownBlocks[2]->source);

        $htmlBlocks = $request->getBlocksByKind('html');
        $this->assertCount(1, $htmlBlocks);
        $this->assertEquals('<p>HTML content</p>', $htmlBlocks[1]->source);
    }

    public function testGetBlocksByKindReturnsEmptyForNonExistentKind(): void
    {
        $blocks = [BlockData::markdown('Content')];
        $request = new ContentCreationRequest('note', null, null, $blocks);

        $result = $request->getBlocksByKind('nonexistent');

        $this->assertEmpty($result);
    }

    public function testHasBlocksOfKindReturnsTrueWhenBlocksExist(): void
    {
        $blocks = [BlockData::markdown('Content')];
        $request = new ContentCreationRequest('note', null, null, $blocks);

        $this->assertTrue($request->hasBlocksOfKind('markdown'));
        $this->assertFalse($request->hasBlocksOfKind('html'));
    }

    public function testGetTotalContentLengthCalculatesCorrectly(): void
    {
        $blocks = [
            BlockData::markdown('12345'),      // 5 chars
            BlockData::markdown('1234567890'),  // 10 chars
        ];

        $request = new ContentCreationRequest('note', null, null, $blocks);

        $this->assertEquals(15, $request->getTotalContentLength());
    }

    public function testGetTotalContentLengthWithEmptyBlocks(): void
    {
        $request = new ContentCreationRequest('note', null, null, []);

        $this->assertEquals(0, $request->getTotalContentLength());
    }

    public function testIsEmptyReturnsTrueForEmptyRequest(): void
    {
        $request = new ContentCreationRequest('note', null, null, []);

        $this->assertTrue($request->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasTitle(): void
    {
        $request = new ContentCreationRequest('note', 'Title', null, []);

        $this->assertFalse($request->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasSummary(): void
    {
        $request = new ContentCreationRequest('note', null, 'Summary', []);

        $this->assertFalse($request->isEmpty());
    }

    public function testIsEmptyReturnsFalseWhenHasContent(): void
    {
        $blocks = [BlockData::markdown('Content')];
        $request = new ContentCreationRequest('note', null, null, $blocks);

        $this->assertFalse($request->isEmpty());
    }

    public function testWithTitleCreatesNewInstance(): void
    {
        $original = new ContentCreationRequest('note', 'Old Title', null, []);

        $updated = $original->withTitle('New Title');

        $this->assertEquals('Old Title', $original->title);
        $this->assertEquals('New Title', $updated->title);
        $this->assertEquals($original->type, $updated->type);
        $this->assertEquals($original->summary, $updated->summary);
        $this->assertEquals($original->blocks, $updated->blocks);
    }

    public function testWithSummaryCreatesNewInstance(): void
    {
        $original = new ContentCreationRequest('note', null, 'Old Summary', []);

        $updated = $original->withSummary('New Summary');

        $this->assertEquals('Old Summary', $original->summary);
        $this->assertEquals('New Summary', $updated->summary);
        $this->assertEquals($original->type, $updated->type);
        $this->assertEquals($original->title, $updated->title);
        $this->assertEquals($original->blocks, $updated->blocks);
    }

    public function testWithBlockCreatesNewInstanceWithAdditionalBlock(): void
    {
        $originalBlocks = [BlockData::markdown('Block 1')];
        $original = new ContentCreationRequest('note', null, null, $originalBlocks);

        $newBlock = BlockData::markdown('Block 2');
        $updated = $original->withBlock($newBlock);

        $this->assertCount(1, $original->blocks);
        $this->assertCount(2, $updated->blocks);
        $this->assertEquals($newBlock, $updated->blocks[1]);
    }

    public function testWithBlocksCreatesNewInstanceWithReplacedBlocks(): void
    {
        $originalBlocks = [BlockData::markdown('Block 1')];
        $original = new ContentCreationRequest('note', null, null, $originalBlocks);

        $newBlocks = [
            BlockData::markdown('New Block 1'),
            BlockData::markdown('New Block 2'),
        ];
        $updated = $original->withBlocks($newBlocks);

        $this->assertEquals($originalBlocks, $original->blocks);
        $this->assertEquals($newBlocks, $updated->blocks);
        $this->assertCount(2, $updated->blocks);
    }
}
