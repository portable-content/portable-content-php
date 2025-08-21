<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Exception;

use PortableContent\Tests\TestCase;
use PortableContent\Exception\ValidationException;

final class ValidationExceptionTest extends TestCase
{
    public function testConstructorSetsErrorsAndMessage(): void
    {
        $errors = [
            'field1' => ['Error 1', 'Error 2'],
            'field2' => ['Error 3']
        ];

        $exception = new ValidationException($errors, 'Custom message');

        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function testConstructorWithDefaultMessage(): void
    {
        $errors = ['field1' => ['Error 1']];

        $exception = new ValidationException($errors);

        $this->assertEquals('Validation failed', $exception->getMessage());
    }

    public function testGetFieldErrorsReturnsCorrectErrors(): void
    {
        $errors = [
            'email' => ['Invalid email format', 'Email already exists'],
            'password' => ['Password too short']
        ];

        $exception = new ValidationException($errors);

        $this->assertEquals(['Invalid email format', 'Email already exists'], $exception->getFieldErrors('email'));
        $this->assertEquals(['Password too short'], $exception->getFieldErrors('password'));
        $this->assertEmpty($exception->getFieldErrors('nonexistent'));
    }

    public function testHasFieldErrorsReturnsTrueWhenFieldHasErrors(): void
    {
        $errors = [
            'username' => ['Required'],
            'email' => ['Invalid format']
        ];

        $exception = new ValidationException($errors);

        $this->assertTrue($exception->hasFieldErrors('username'));
        $this->assertTrue($exception->hasFieldErrors('email'));
        $this->assertFalse($exception->hasFieldErrors('password'));
    }

    public function testGetAllMessagesFormatsCorrectly(): void
    {
        $errors = [
            'name' => ['Name is required'],
            'email' => ['Invalid email', 'Email too long']
        ];

        $exception = new ValidationException($errors);
        $messages = $exception->getAllMessages();

        $this->assertCount(3, $messages);
        $this->assertContains('name: Name is required', $messages);
        $this->assertContains('email: Invalid email', $messages);
        $this->assertContains('email: Email too long', $messages);
    }

    public function testGetErrorCountReturnsCorrectTotal(): void
    {
        $errors = [
            'field1' => ['Error 1', 'Error 2', 'Error 3'],
            'field2' => ['Error 4'],
            'field3' => ['Error 5', 'Error 6']
        ];

        $exception = new ValidationException($errors);

        $this->assertEquals(6, $exception->getErrorCount());
    }

    public function testGetErrorCountWithEmptyErrors(): void
    {
        $exception = new ValidationException([]);

        $this->assertEquals(0, $exception->getErrorCount());
    }

    public function testSingleErrorFactoryMethod(): void
    {
        $exception = ValidationException::singleError('username', 'Username is required');

        $this->assertEquals(['username' => ['Username is required']], $exception->getErrors());
        $this->assertEquals('Validation failed: username: Username is required', $exception->getMessage());
        $this->assertEquals(1, $exception->getErrorCount());
        $this->assertTrue($exception->hasFieldErrors('username'));
    }

    public function testMultipleErrorsFactoryMethod(): void
    {
        $errors = [
            'field1' => ['Error 1', 'Error 2'],
            'field2' => ['Error 3']
        ];

        $exception = ValidationException::multipleErrors($errors);

        $this->assertEquals($errors, $exception->getErrors());
        $this->assertEquals('Validation failed with 3 error(s)', $exception->getMessage());
        $this->assertEquals(3, $exception->getErrorCount());
    }

    public function testMultipleErrorsWithEmptyErrors(): void
    {
        $exception = ValidationException::multipleErrors([]);

        $this->assertEquals([], $exception->getErrors());
        $this->assertEquals('Validation failed with 0 error(s)', $exception->getMessage());
        $this->assertEquals(0, $exception->getErrorCount());
    }

    public function testExceptionInheritance(): void
    {
        $exception = new ValidationException(['field' => ['error']]);

        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(\Exception::class, $exception);
    }

    public function testExceptionWithCodeAndPrevious(): void
    {
        $previous = new \Exception('Previous exception');
        $exception = new ValidationException(['field' => ['error']], 'Test message', 123, $previous);

        $this->assertEquals(123, $exception->getCode());
        $this->assertSame($previous, $exception->getPrevious());
    }
}
