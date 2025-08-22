<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation\ValueObjects;

use PortableContent\Tests\TestCase;
use PortableContent\Validation\ValueObjects\ValidationResult;

/**
 * @internal
 */
final class ValidationResultTest extends TestCase
{
    public function testSuccessCreatesValidResult(): void
    {
        $result = ValidationResult::success();

        $this->assertTrue($result->isValid());
        $this->assertEmpty($result->getErrors());
        $this->assertFalse($result->hasErrors());
        $this->assertEquals(0, $result->getErrorCount());
        $this->assertEmpty($result->getFieldsWithErrors());
        $this->assertEmpty($result->getAllMessages());
    }

    public function testFailureCreatesInvalidResult(): void
    {
        $errors = [
            'field1' => ['Error 1', 'Error 2'],
            'field2' => ['Error 3'],
        ];

        $result = ValidationResult::failure($errors);

        $this->assertFalse($result->isValid());
        $this->assertEquals($errors, $result->getErrors());
        $this->assertTrue($result->hasErrors());
        $this->assertEquals(3, $result->getErrorCount());
        $this->assertEquals(['field1', 'field2'], $result->getFieldsWithErrors());
    }

    public function testSingleErrorCreatesInvalidResult(): void
    {
        $result = ValidationResult::singleError('username', 'Username is required');

        $this->assertFalse($result->isValid());
        $this->assertEquals(['username' => ['Username is required']], $result->getErrors());
        $this->assertTrue($result->hasFieldErrors('username'));
        $this->assertEquals(['Username is required'], $result->getFieldErrors('username'));
        $this->assertEquals(1, $result->getErrorCount());
    }

    public function testGetFieldErrorsReturnsEmptyArrayForNonExistentField(): void
    {
        $result = ValidationResult::success();

        $this->assertEmpty($result->getFieldErrors('nonexistent'));
        $this->assertFalse($result->hasFieldErrors('nonexistent'));
    }

    public function testGetFieldErrorsReturnsCorrectErrors(): void
    {
        $errors = [
            'email' => ['Invalid email format', 'Email already exists'],
            'password' => ['Password too short'],
        ];

        $result = ValidationResult::failure($errors);

        $this->assertEquals(['Invalid email format', 'Email already exists'], $result->getFieldErrors('email'));
        $this->assertEquals(['Password too short'], $result->getFieldErrors('password'));
        $this->assertTrue($result->hasFieldErrors('email'));
        $this->assertTrue($result->hasFieldErrors('password'));
        $this->assertFalse($result->hasFieldErrors('username'));
    }

    public function testGetAllMessagesFormatsCorrectly(): void
    {
        $errors = [
            'name' => ['Name is required'],
            'email' => ['Invalid email', 'Email too long'],
        ];

        $result = ValidationResult::failure($errors);
        $messages = $result->getAllMessages();

        $this->assertCount(3, $messages);
        $this->assertContains('name: Name is required', $messages);
        $this->assertContains('email: Invalid email', $messages);
        $this->assertContains('email: Email too long', $messages);
    }

    public function testMergeWithTwoSuccessfulResults(): void
    {
        $result1 = ValidationResult::success();
        $result2 = ValidationResult::success();

        $merged = $result1->merge($result2);

        $this->assertTrue($merged->isValid());
        $this->assertEmpty($merged->getErrors());
    }

    public function testMergeWithOneFailedResult(): void
    {
        $result1 = ValidationResult::success();
        $result2 = ValidationResult::failure(['field1' => ['Error 1']]);

        $merged = $result1->merge($result2);

        $this->assertFalse($merged->isValid());
        $this->assertEquals(['field1' => ['Error 1']], $merged->getErrors());
    }

    public function testMergeWithTwoFailedResults(): void
    {
        $result1 = ValidationResult::failure(['field1' => ['Error 1']]);
        $result2 = ValidationResult::failure(['field2' => ['Error 2']]);

        $merged = $result1->merge($result2);

        $this->assertFalse($merged->isValid());
        $this->assertEquals([
            'field1' => ['Error 1'],
            'field2' => ['Error 2'],
        ], $merged->getErrors());
        $this->assertEquals(2, $merged->getErrorCount());
    }

    public function testMergeWithOverlappingFields(): void
    {
        $result1 = ValidationResult::failure(['field1' => ['Error 1']]);
        $result2 = ValidationResult::failure(['field1' => ['Error 2'], 'field2' => ['Error 3']]);

        $merged = $result1->merge($result2);

        $this->assertFalse($merged->isValid());
        $this->assertEquals([
            'field1' => ['Error 1', 'Error 2'],
            'field2' => ['Error 3'],
        ], $merged->getErrors());
        $this->assertEquals(3, $merged->getErrorCount());
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $errors = ['field1' => ['Error 1', 'Error 2']];
        $result = ValidationResult::failure($errors);

        $array = $result->toArray();

        $this->assertEquals([
            'isValid' => false,
            'errors' => $errors,
            'errorCount' => 2,
            'fieldsWithErrors' => ['field1'],
        ], $array);
    }

    public function testToArrayForSuccessResult(): void
    {
        $result = ValidationResult::success();

        $array = $result->toArray();

        $this->assertEquals([
            'isValid' => true,
            'errors' => [],
            'errorCount' => 0,
            'fieldsWithErrors' => [],
        ], $array);
    }

    public function testGetErrorCountWithMultipleFields(): void
    {
        $errors = [
            'field1' => ['Error 1', 'Error 2', 'Error 3'],
            'field2' => ['Error 4'],
            'field3' => ['Error 5', 'Error 6'],
        ];

        $result = ValidationResult::failure($errors);

        $this->assertEquals(6, $result->getErrorCount());
    }

    public function testGetFieldsWithErrorsReturnsCorrectFields(): void
    {
        $errors = [
            'username' => ['Required'],
            'email' => ['Invalid format'],
            'password' => ['Too short', 'Missing special character'],
        ];

        $result = ValidationResult::failure($errors);

        $fieldsWithErrors = $result->getFieldsWithErrors();
        $this->assertCount(3, $fieldsWithErrors);
        $this->assertContains('username', $fieldsWithErrors);
        $this->assertContains('email', $fieldsWithErrors);
        $this->assertContains('password', $fieldsWithErrors);
    }

    public function testEmptyErrorsArrayCreatesValidResult(): void
    {
        $result = ValidationResult::failure([]);

        $this->assertFalse($result->isValid());
        $this->assertFalse($result->hasErrors());
        $this->assertEquals(0, $result->getErrorCount());
        $this->assertEmpty($result->getFieldsWithErrors());
    }
}
