<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use PortableContent\Exception\ContentNotFoundException;

/**
 * @internal
 *
 */
final class ContentNotFoundExceptionTest extends TestCase
{
    public function testConstructorSetsCorrectMessage(): void
    {
        $contentId = 'test-content-id';

        $exception = new ContentNotFoundException($contentId);

        $this->assertEquals("Content with ID 'test-content-id' not found", $exception->getMessage());
    }

    public function testExceptionInheritance(): void
    {
        $exception = new ContentNotFoundException('test');

        $this->assertInstanceOf(\RuntimeException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testWithEmptyContentId(): void
    {
        $exception = new ContentNotFoundException('');

        $this->assertEquals("Content with ID '' not found", $exception->getMessage());
    }

    public function testWithSpecialCharacters(): void
    {
        $contentId = "test'id\"with<special>&chars";

        $exception = new ContentNotFoundException($contentId);

        $this->assertStringContainsString($contentId, $exception->getMessage());
        $this->assertEquals("Content with ID 'test'id\"with<special>&chars' not found", $exception->getMessage());
    }

    public function testExceptionIsFinal(): void
    {
        $reflection = new \ReflectionClass(ContentNotFoundException::class);

        $this->assertTrue($reflection->isFinal());
    }
}
