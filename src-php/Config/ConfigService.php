<?php

declare(strict_types=1);

namespace Jumpnrun\Config;

/**
 * Config-Merge in dieser Reihenfolge (spaetere ueberschreiben fruehere):
 *   1. ConfigSchema::defaults()
 *   2. wp_options['jumpnrun_config']   (Settings-Page-Felder)
 *   3. configRawJson aus dem gleichen wp_option (Advanced-Override)
 *
 * Frontend + REST + Shortcode lesen alle ueber getConfig().
 */
final class ConfigService
{
    public const OPTION_KEY = 'jumpnrun_config';

    /** @var array<string, int|float|string|bool>|null */
    private static ?array $cache = null;

    /** Fuer Tests + nach Settings-Save. */
    public static function clearCache(): void
    {
        self::$cache = null;
    }

    /** @return array<string, int|float|string|bool> */
    public static function getConfig(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $merged = ConfigSchema::defaults();

        $stored = get_option(self::OPTION_KEY, []);
        if (is_array($stored)) {
            foreach (ConfigSchema::fieldMap() as $key => $spec) {
                if (array_key_exists($key, $stored)) {
                    $merged[$key] = ConfigSchema::sanitizeValue($stored[$key], $spec);
                }
            }
        }

        $rawJson = $merged['configRawJson'] ?? '';
        if (is_string($rawJson) && $rawJson !== '') {
            $decoded = json_decode($rawJson, true);
            if (is_array($decoded)) {
                foreach ($decoded as $key => $value) {
                    if (is_string($key) && isset(ConfigSchema::fieldMap()[$key])) {
                        $spec = ConfigSchema::fieldMap()[$key];
                        $merged[$key] = ConfigSchema::sanitizeValue($value, $spec);
                    }
                }
            }
        }

        // configRawJson selbst gehoert nicht in die Engine-Config.
        unset($merged['configRawJson']);

        self::$cache = $merged;
        return $merged;
    }

    /**
     * Auswaehlen der anti-cheat-relevanten Felder fuer REST-Endpoints.
     *
     * @return array{rateLimitSessionPerMin:int, rateLimitScorePerMin:int, minSessionDurationSec:int}
     */
    public static function antiCheatLimits(): array
    {
        $cfg = self::getConfig();
        return [
            'rateLimitSessionPerMin' => (int) $cfg['rateLimitSessionPerMin'],
            'rateLimitScorePerMin' => (int) $cfg['rateLimitScorePerMin'],
            'minSessionDurationSec' => (int) $cfg['minSessionDurationSec'],
        ];
    }

    /** @return array{playerNameMaxLength:int, discountCode:string, discountLevel:int} */
    public static function gameDefaults(): array
    {
        $cfg = self::getConfig();
        return [
            'playerNameMaxLength' => 15,
            'discountCode' => (string) $cfg['discountCode'],
            'discountLevel' => (int) $cfg['discountLevel'],
        ];
    }

    /**
     * Teil der Config, der an die JS-Engine geht (ohne Backend-Only-Felder
     * wie antiCheat-Limits oder Layout-Flags).
     *
     * @return array<string, int|float|string|bool>
     */
    public static function engineConfig(): array
    {
        $cfg = self::getConfig();
        $nonEngine = [
            'rateLimitSessionPerMin',
            'rateLimitScorePerMin',
            'minSessionDurationSec',
            'showScoreboard',
            'scoreboardLimit',
            'playerIdleSprite',
            'playerJumpSprite',
            'coinSprite',
        ];
        foreach ($nonEngine as $key) {
            unset($cfg[$key]);
        }
        return $cfg;
    }

    /** @return array{enabled:bool, limit:int} */
    public static function scoreboardConfig(): array
    {
        $cfg = self::getConfig();
        return [
            'enabled' => (bool) ($cfg['showScoreboard'] ?? true),
            'limit' => (int) ($cfg['scoreboardLimit'] ?? 10),
        ];
    }

    /**
     * URLs fuer die im Admin per Media-Picker zugewiesenen Sprites.
     * Schluessel = sprite-image-key (passend zu den Default-PNGs in `assets/sprites/`).
     * Wert = absolute URL des Attachments. Felder ohne Bild fehlen im Array.
     *
     * @return array<string, string>
     */
    public static function spriteOverrides(): array
    {
        $cfg = self::getConfig();
        $map = [
            'player-idle' => (int) ($cfg['playerIdleSprite'] ?? 0),
            'player-jump' => (int) ($cfg['playerJumpSprite'] ?? 0),
            'coin' => (int) ($cfg['coinSprite'] ?? 0),
        ];
        $out = [];
        foreach ($map as $key => $id) {
            if ($id <= 0) {
                continue;
            }
            $url = wp_get_attachment_url($id);
            if (is_string($url) && $url !== '') {
                $out[$key] = $url;
            }
        }
        return $out;
    }
}
