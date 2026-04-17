<?php

declare(strict_types=1);

namespace Jumpnrun\Tests\AntiCheat;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Jumpnrun\AntiCheat\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testAllowsRequestsUpToLimit(): void
    {
        $store = ['value' => 0];

        Functions\when('get_transient')->alias(static fn () => $store['value']);
        Functions\when('set_transient')->alias(
            static function ($_key, $value) use (&$store): bool {
                $store['value'] = $value;
                return true;
            }
        );

        $limiter = new RateLimiter('test', 3);
        $hash = str_repeat('a', 64);

        self::assertTrue($limiter->allow($hash));
        self::assertTrue($limiter->allow($hash));
        self::assertTrue($limiter->allow($hash));
    }

    public function testBlocksWhenLimitReached(): void
    {
        Functions\when('get_transient')->justReturn(5);
        Functions\when('set_transient')->justReturn(true);

        $limiter = new RateLimiter('test', 5);
        self::assertFalse($limiter->allow(str_repeat('a', 64)));
    }

    public function testAllowsFirstRequestWhenBucketEmpty(): void
    {
        Functions\when('get_transient')->justReturn(false);
        Functions\when('set_transient')->justReturn(true);

        $limiter = new RateLimiter('test', 10);
        self::assertTrue($limiter->allow(str_repeat('b', 64)));
    }
}
