<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Exception;

use PHPUnit\Framework\TestCase;
use PortableContent\Exception\InvalidContentException;

/**
 * @internal
 */
final class InvalidContentExceptionTest extends TestCase
{
    public function testExtendsInvalidArgumentException(): void
    {
        $exception = InvalidContentException::emptyType();

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
    }

    public function testEmptyType(): void
    {
        $exception = InvalidContentException::emptyType();

        $this->assertEquals('Content type cannot be empty', $exception->getMessage());
    }

    public function testEmptyBlockSource(): void
    {
        $exception = InvalidContentException::emptyBlockSource();

        $this->assertEquals('Block source cannot be empty', $exception->getMessage());
    }

    public function testInvalidBlockTypeWithString(): void
    {
        $exception = InvalidContentException::invalidBlockType('invalid');

        $this->assertEquals('Expected BlockInterface implementation, got string', $exception->getMessage());
    }

    public function testInvalidBlockTypeWithObject(): void
    {
        $exception = InvalidContentException::invalidBlockType(new \stdClass());

        $this->assertEquals('Expected BlockInterface implementation, got stdClass', $exception->getMessage());
    }

    public function testInvalidBlockTypeWithArray(): void
    {
        $exception = InvalidContentException::invalidBlockType([]);

        $this->assertEquals('Expected BlockInterface implementation, got array', $exception->getMessage());
    }

    public function testInvalidBlockTypeWithNull(): void
    {
        $exception = InvalidContentException::invalidBlockType(null);

        $this->assertEquals('Expected BlockInterface implementation, got null', $exception->getMessage());
    }
}
