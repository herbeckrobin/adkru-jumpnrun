<?php

declare(strict_types=1);

namespace Jumpnrun\Tests\Config;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Jumpnrun\Config\ConfigSchema;
use Jumpnrun\Config\ConfigService;
use PHPUnit\Framework\TestCase;

final class ConfigServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ConfigService::clearCache();
        // sanitizeValue ruft WP-Funktionen fuer Strings/URLs auf.
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();
    }

    protected function tearDown(): void
    {
        ConfigService::clearCache();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testReturnsSchemaDefaultsWhenNoOption(): void
    {
        Functions\when('get_option')->justReturn([]);

        $cfg = ConfigService::getConfig();
        $defaults = ConfigSchema::defaults();
        unset($defaults['configRawJson']);

        foreach ($defaults as $key => $value) {
            self::assertSame($value, $cfg[$key], "Default $key falsch");
        }
    }

    public function testOptionOverridesDefaults(): void
    {
        Functions\when('get_option')->justReturn(['gravity' => 2.5, 'maxJumps' => 3]);

        $cfg = ConfigService::getConfig();

        self::assertSame(2.5, $cfg['gravity']);
        self::assertSame(3, $cfg['maxJumps']);
        // Andere Defaults bleiben.
        self::assertSame(960, $cfg['canvasWidth']);
    }

    public function testOptionValuesAreClamped(): void
    {
        // gravity hat max 5 — Wert 99 soll auf 5 geclampt werden.
        Functions\when('get_option')->justReturn(['gravity' => 99]);

        $cfg = ConfigService::getConfig();
        self::assertSame(5.0, $cfg['gravity']);
    }

    public function testRawJsonOverridesOption(): void
    {
        Functions\when('get_option')->justReturn([
            'gravity' => 2.0,
            'configRawJson' => '{"gravity": 3.5}',
        ]);

        $cfg = ConfigService::getConfig();
        self::assertSame(3.5, $cfg['gravity']);
    }

    public function testRawJsonWithInvalidJsonIsIgnored(): void
    {
        Functions\when('get_option')->justReturn([
            'gravity' => 2.0,
            'configRawJson' => 'not json',
        ]);

        $cfg = ConfigService::getConfig();
        // Option-Wert bleibt, invalid JSON wird ignoriert.
        self::assertSame(2.0, $cfg['gravity']);
    }

    public function testConfigRawJsonNotPresentInOutput(): void
    {
        Functions\when('get_option')->justReturn(['configRawJson' => '{"gravity":1}']);

        $cfg = ConfigService::getConfig();
        self::assertArrayNotHasKey('configRawJson', $cfg);
    }

    public function testEngineConfigStripsAntiCheatKeys(): void
    {
        Functions\when('get_option')->justReturn([]);

        $engine = ConfigService::engineConfig();
        self::assertArrayNotHasKey('rateLimitSessionPerMin', $engine);
        self::assertArrayNotHasKey('rateLimitScorePerMin', $engine);
        self::assertArrayNotHasKey('minSessionDurationSec', $engine);
        // Engine-Keys bleiben drin.
        self::assertArrayHasKey('gravity', $engine);
    }

    public function testAntiCheatLimitsAreInts(): void
    {
        Functions\when('get_option')->justReturn([]);

        $limits = ConfigService::antiCheatLimits();
        self::assertIsInt($limits['rateLimitSessionPerMin']);
        self::assertIsInt($limits['rateLimitScorePerMin']);
        self::assertIsInt($limits['minSessionDurationSec']);
    }
}
