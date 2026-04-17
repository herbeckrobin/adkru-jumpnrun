<?php

declare(strict_types=1);

namespace Jumpnrun\Tests\Config;

use Jumpnrun\Config\ConfigSchema;
use PHPUnit\Framework\TestCase;

final class ConfigSchemaTest extends TestCase
{
    public function testAllSectionsHaveLabelAndFields(): void
    {
        foreach (ConfigSchema::all() as $slug => $section) {
            self::assertArrayHasKey('label', $section, "Section $slug ohne label");
            self::assertArrayHasKey('fields', $section, "Section $slug ohne fields");
            self::assertNotEmpty($section['fields'], "Section $slug ist leer");
        }
    }

    public function testDefaultsContainsAllSectionFields(): void
    {
        $defaults = ConfigSchema::defaults();
        foreach (ConfigSchema::all() as $section) {
            foreach ($section['fields'] as $key => $_spec) {
                self::assertArrayHasKey($key, $defaults, "Default fuer $key fehlt");
            }
        }
    }

    public function testNoDuplicateFieldKeysAcrossSections(): void
    {
        $seen = [];
        foreach (ConfigSchema::all() as $slug => $section) {
            foreach ($section['fields'] as $key => $_spec) {
                self::assertArrayNotHasKey(
                    $key,
                    $seen,
                    "Key '$key' in Section '$slug' kollidiert mit Section '".($seen[$key] ?? '')."'"
                );
                $seen[$key] = $slug;
            }
        }
    }

    public function testSanitizeIntClampsToRange(): void
    {
        $spec = ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 100];
        self::assertSame(1, ConfigSchema::sanitizeValue(-5, $spec));
        self::assertSame(100, ConfigSchema::sanitizeValue(999, $spec));
        self::assertSame(42, ConfigSchema::sanitizeValue(42, $spec));
    }

    public function testSanitizeFloatClampsToRange(): void
    {
        $spec = ['type' => 'float', 'default' => 1.5, 'min' => 0.0, 'max' => 5.0];
        self::assertSame(0.0, ConfigSchema::sanitizeValue(-10, $spec));
        self::assertSame(5.0, ConfigSchema::sanitizeValue(99.9, $spec));
        self::assertSame(2.5, ConfigSchema::sanitizeValue(2.5, $spec));
    }

    public function testSanitizeBoolCoercesTruthy(): void
    {
        $spec = ['type' => 'bool', 'default' => false];
        self::assertTrue(ConfigSchema::sanitizeValue('1', $spec));
        self::assertTrue(ConfigSchema::sanitizeValue(1, $spec));
        self::assertFalse(ConfigSchema::sanitizeValue('', $spec));
        self::assertFalse(ConfigSchema::sanitizeValue(0, $spec));
    }
}
