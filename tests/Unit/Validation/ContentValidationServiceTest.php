<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit\Validation;

use PortableContent\Contracts\ContentValidatorInterface;
use PortableContent\Contracts\SanitizerInterface;
use PortableContent\Tests\TestCase;
use PortableContent\Validation\ContentValidationService;
use PortableContent\Validation\ValueObjects\ValidationResult;

/**
 * @internal
 *
 * @coversNothing
 */
final class ContentValidationServiceTest extends TestCase
{
    private ContentValidationService $service;
    private SanitizerInterface $mockSanitizer;
    private ContentValidatorInterface $mockValidator;

    protected function setUp(): void
    {
        parent::setUp();

        // Create mock sanitizer
        $this->mockSanitizer = $this->createMock(SanitizerInterface::class);

        // Create mock validator
        $this->mockValidator = $this->createMock(ContentValidatorInterface::class);

        // Create service with mocks
        $this->service = new ContentValidationService(
            $this->mockSanitizer,
            $this->mockValidator
        );
    }

    public function testValidateContentCreationSuccess(): void
    {
        $inputData = ['type' => 'note', 'title' => 'Test'];
        $sanitizedData = ['type' => 'note', 'title' => 'Test'];
        $validationResult = ValidationResult::success();

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willReturn($sanitizedData)
        ;

        $this->mockValidator
            ->expects($this->once())
            ->method('validateContentCreation')
            ->with($sanitizedData)
            ->willReturn($validationResult)
        ;

        $result = $this->service->validateContentCreation($inputData);

        $this->assertTrue($result->isValid());
        $this->assertEquals($sanitizedData, $result->getData());
    }

    public function testValidateContentCreationValidationFailure(): void
    {
        $inputData = ['type' => 'note'];
        $sanitizedData = ['type' => 'note'];
        $validationResult = ValidationResult::singleError('title', 'Title is required');

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willReturn($sanitizedData)
        ;

        $this->mockValidator
            ->expects($this->once())
            ->method('validateContentCreation')
            ->with($sanitizedData)
            ->willReturn($validationResult)
        ;

        $result = $this->service->validateContentCreation($inputData);

        $this->assertFalse($result->isValid());
        $this->assertEquals(['title' => ['Title is required']], $result->getErrors());
    }

    public function testValidateContentCreationSanitizationError(): void
    {
        $inputData = ['blocks' => 'invalid'];

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willThrowException(new \InvalidArgumentException('Invalid blocks data'))
        ;

        $this->mockValidator
            ->expects($this->never())
            ->method('validateContentCreation')
        ;

        $result = $this->service->validateContentCreation($inputData);

        $this->assertFalse($result->isValid());
        $this->assertEquals(['sanitization' => ['Invalid blocks data']], $result->getErrors());
    }

    public function testValidateContentUpdateSuccess(): void
    {
        $inputData = ['type' => 'note', 'title' => 'Updated'];
        $sanitizedData = ['type' => 'note', 'title' => 'Updated'];
        $validationResult = ValidationResult::success();

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willReturn($sanitizedData)
        ;

        $this->mockValidator
            ->expects($this->once())
            ->method('validateContentUpdate')
            ->with($sanitizedData)
            ->willReturn($validationResult)
        ;

        $result = $this->service->validateContentUpdate($inputData);

        $this->assertTrue($result->isValid());
        $this->assertEquals($sanitizedData, $result->getData());
    }

    public function testSanitizeContent(): void
    {
        $inputData = ['type' => '  note  ', 'title' => 'Test'];
        $sanitizedData = ['type' => 'note', 'title' => 'Test'];

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willReturn($sanitizedData)
        ;

        $result = $this->service->sanitizeContent($inputData);

        $this->assertEquals($sanitizedData, $result);
    }

    public function testSanitizeContentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid data');

        $inputData = ['invalid' => 'data'];

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willThrowException(new \InvalidArgumentException('Invalid data'))
        ;

        $this->service->sanitizeContent($inputData);
    }

    public function testGetSanitizationStats(): void
    {
        $originalData = ['type' => '  note  '];
        $sanitizedData = ['type' => 'note'];
        $expectedStats = ['fields_modified' => 1];

        $this->mockSanitizer
            ->expects($this->once())
            ->method('getSanitizationStats')
            ->with($originalData, $sanitizedData)
            ->willReturn($expectedStats)
        ;

        $result = $this->service->getSanitizationStats($originalData, $sanitizedData);

        $this->assertEquals($expectedStats, $result);
    }

    public function testValidateSanitizedContent(): void
    {
        $sanitizedData = ['type' => 'note', 'title' => 'Test'];
        $validationResult = ValidationResult::success();

        $this->mockValidator
            ->expects($this->once())
            ->method('validateContentCreation')
            ->with($sanitizedData)
            ->willReturn($validationResult)
        ;

        $result = $this->service->validateSanitizedContent($sanitizedData);

        $this->assertTrue($result->isValid());
    }

    public function testProcessContentWithDetailsSuccess(): void
    {
        $inputData = ['type' => '  note  ', 'title' => 'Test'];
        $sanitizedData = ['type' => 'note', 'title' => 'Test'];
        $stats = ['fields_modified' => 1];
        $validationResult = ValidationResult::success();

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willReturn($sanitizedData)
        ;

        $this->mockSanitizer
            ->expects($this->once())
            ->method('getSanitizationStats')
            ->with($inputData, $sanitizedData)
            ->willReturn($stats)
        ;

        $this->mockValidator
            ->expects($this->once())
            ->method('validateContentCreation')
            ->with($sanitizedData)
            ->willReturn($validationResult)
        ;

        $result = $this->service->processContentWithDetails($inputData);

        $this->assertEquals($sanitizedData, $result['sanitized_data']);
        $this->assertEquals($stats, $result['sanitization_stats']);
        $this->assertTrue($result['validation_result']->isValid());
        $this->assertTrue($result['final_result']->isValid());
        $this->assertEquals($sanitizedData, $result['final_result']->getData());
    }

    public function testProcessContentWithDetailsValidationFailure(): void
    {
        $inputData = ['type' => 'note'];
        $sanitizedData = ['type' => 'note'];
        $stats = ['fields_modified' => 0];
        $validationResult = ValidationResult::singleError('title', 'Title is required');

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willReturn($sanitizedData)
        ;

        $this->mockSanitizer
            ->expects($this->once())
            ->method('getSanitizationStats')
            ->with($inputData, $sanitizedData)
            ->willReturn($stats)
        ;

        $this->mockValidator
            ->expects($this->once())
            ->method('validateContentCreation')
            ->with($sanitizedData)
            ->willReturn($validationResult)
        ;

        $result = $this->service->processContentWithDetails($inputData);

        $this->assertEquals($sanitizedData, $result['sanitized_data']);
        $this->assertEquals($stats, $result['sanitization_stats']);
        $this->assertFalse($result['validation_result']->isValid());
        $this->assertFalse($result['final_result']->isValid());
    }

    public function testProcessContentWithDetailsSanitizationError(): void
    {
        $inputData = ['blocks' => 'invalid'];

        $this->mockSanitizer
            ->expects($this->once())
            ->method('sanitize')
            ->with($inputData)
            ->willThrowException(new \InvalidArgumentException('Invalid blocks'))
        ;

        $result = $this->service->processContentWithDetails($inputData);

        $this->assertEquals([], $result['sanitized_data']);
        $this->assertEquals([], $result['sanitization_stats']);
        $this->assertFalse($result['validation_result']->isValid());
        $this->assertFalse($result['final_result']->isValid());
        $this->assertEquals(['sanitization' => ['Invalid blocks']], $result['final_result']->getErrors());
    }
}
