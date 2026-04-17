<?php

declare(strict_types=1);

namespace Jumpnrun\Tests\Config;

use Jumpnrun\Config\ConfigService;
use PHPUnit\Framework\TestCase;

final class ConfigServiceTest extends TestCase
{
    public function testAntiCheatLimitsHaveAllRequiredKeys(): void
    {
        $limits = ConfigService::antiCheatLimits();

        self::assertArrayHasKey('rateLimitSessionPerMin', $limits);
        self::assertArrayHasKey('rateLimitScorePerMin', $limits);
        self::assertArrayHasKey('minSessionDurationSec', $limits);
    }

    public function testAntiCheatLimitsAreSaneDefaults(): void
    {
        $limits = ConfigService::antiCheatLimits();

        self::assertGreaterThan(0, $limits['rateLimitSessionPerMin']);
        self::assertGreaterThan(0, $limits['rateLimitScorePerMin']);
        self::assertGreaterThanOrEqual(
            $limits['rateLimitSessionPerMin'],
            $limits['rateLimitScorePerMin'],
            'Score-Limit sollte >= Session-Limit sein (mehrere Submits pro Session moeglich)'
        );
        self::assertGreaterThan(0, $limits['minSessionDurationSec']);
    }

    public function testGameDefaultsHaveBrandingFields(): void
    {
        $defaults = ConfigService::gameDefaults();

        self::assertArrayHasKey('playerNameMaxLength', $defaults);
        self::assertArrayHasKey('discountCode', $defaults);
        self::assertArrayHasKey('discountLevel', $defaults);
        self::assertSame('BURNERKING20', $defaults['discountCode']);
    }
}
