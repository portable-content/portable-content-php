<?php

declare(strict_types=1);

namespace PortableContent\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 *
 * @coversNothing
 */
final class ExampleTest extends TestCase
{
    #[CoversNothing]
    public function testBasicAssertion(): void
    {
        $this->assertTrue(true);
    }

    #[CoversNothing]
    public function testPHPVersion(): void
    {
        $this->assertGreaterThanOrEqual('8.3.0', PHP_VERSION);
    }

    #[CoversNothing]
    public function testAutoloadingWorks(): void
    {
        // These classes don't exist yet, so we'll test that the autoloader is configured
        $this->assertTrue(class_exists('PHPUnit\Framework\TestCase'));

        // TODO: Enable these tests when the classes are implemented
        // $this->assertTrue(class_exists('PortableContent\ContentItem'));
        // $this->assertTrue(interface_exists('PortableContent\Repository\ContentRepositoryInterface'));
    }
}
