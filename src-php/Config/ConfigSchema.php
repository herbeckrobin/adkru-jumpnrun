<?php

declare(strict_types=1);

namespace Jumpnrun\Config;

/**
 * Single-Source-of-Truth fuer alle im Admin editierbaren Tuning-Werte.
 *
 * Struktur: Sections → Felder. Jedes Feld liefert Typ, Default, Range und
 * Label. Die SettingsPage iteriert darueber und rendert passende Inputs.
 * Keys spiegeln die Engine-Config-Keys (siehe src-js/engine/config/index.ts).
 *
 * @phpstan-type FieldType 'int'|'float'|'string'|'bool'|'url'|'textarea'
 * @phpstan-type FieldSpec array{
 *   type: FieldType,
 *   default: int|float|string|bool,
 *   label: string,
 *   help?: string,
 *   min?: int|float,
 *   max?: int|float,
 *   step?: int|float,
 *   maxlen?: int,
 * }
 */
final class ConfigSchema
{
    /** @return array<string, array{label:string, fields: array<string, array<string, mixed>>}> */
    public static function all(): array
    {
        return [
            'canvas' => [
                'label' => 'Canvas',
                'fields' => [
                    'canvasWidth'  => ['type' => 'int', 'default' => 960, 'min' => 320, 'max' => 1920, 'step' => 10, 'label' => 'Breite (px)'],
                    'canvasHeight' => ['type' => 'int', 'default' => 540, 'min' => 240, 'max' => 1080, 'step' => 10, 'label' => 'Hoehe (px)'],
                ],
            ],
            'physics' => [
                'label' => 'Physik',
                'fields' => [
                    'gravity'      => ['type' => 'float', 'default' => 1.8,  'min' => 0.5, 'max' => 5,  'step' => 0.1, 'label' => 'Gravitation (px/frame)'],
                    'jumpVelocity' => ['type' => 'float', 'default' => -22,  'min' => -40, 'max' => -5, 'step' => 0.5, 'label' => 'Sprungkraft (negativ = nach oben)'],
                    'maxJumps'     => ['type' => 'int',   'default' => 2,    'min' => 1,   'max' => 5,  'step' => 1,   'label' => 'Max. Spruenge in Folge'],
                ],
            ],
            'hitbox' => [
                'label' => 'Hitbox',
                'fields' => [
                    'hitboxBuffer' => ['type' => 'int', 'default' => 10, 'min' => -20, 'max' => 40, 'step' => 1, 'label' => 'Obstacle-Hitbox-Puffer (px)', 'help' => 'Positiv = Hindernis-Hitbox wird auf jeder Seite kleiner, der Spieler kommt naeher ran. Negativ = grosszuegiger gegen den Spieler.'],
                    'coinMagnet'   => ['type' => 'int', 'default' => 10, 'min' => 0,   'max' => 40, 'step' => 1, 'label' => 'Coin-Magnet (px)',            'help' => 'Wie viele Pixel ausserhalb der sichtbaren Coin noch eingesammelt werden.'],
                ],
            ],
            'world' => [
                'label' => 'Welt',
                'fields' => [
                    'baseSpeed'         => ['type' => 'float', 'default' => 5,    'min' => 1,   'max' => 20,  'step' => 0.5, 'label' => 'Basis-Scrollgeschwindigkeit (px/frame)'],
                    'speedPerLevel'     => ['type' => 'float', 'default' => 0.5,  'min' => 0,   'max' => 5,   'step' => 0.1, 'label' => 'Tempo-Zuwachs pro Level'],
                    'bgScrollBase'      => ['type' => 'float', 'default' => 2,    'min' => 0,   'max' => 10,  'step' => 0.5, 'label' => 'Hintergrund-Scroll-Basis'],
                    'bgScrollPerLevel'  => ['type' => 'float', 'default' => 0.4,  'min' => 0,   'max' => 5,   'step' => 0.1, 'label' => 'Hintergrund-Scroll pro Level'],
                    'coinsPerLevel'     => ['type' => 'int',   'default' => 5,    'min' => 1,   'max' => 50,  'step' => 1,   'label' => 'Coins bis Level-Up'],
                    'maxLevels'         => ['type' => 'int',   'default' => 10,   'min' => 1,   'max' => 99,  'step' => 1,   'label' => 'Max. Level'],
                    'groundOffset'      => ['type' => 'int',   'default' => 60,   'min' => 0,   'max' => 200, 'step' => 5,   'label' => 'Boden-Offset (px vom unteren Rand)'],
                ],
            ],
            'player' => [
                'label' => 'Spieler',
                'fields' => [
                    'playerWidth'  => ['type' => 'int', 'default' => 70,  'min' => 30, 'max' => 200, 'step' => 2, 'label' => 'Breite (px)'],
                    'playerHeight' => ['type' => 'int', 'default' => 70,  'min' => 30, 'max' => 200, 'step' => 2, 'label' => 'Hoehe (px)'],
                    'playerStartX' => ['type' => 'int', 'default' => 120, 'min' => 0,  'max' => 800, 'step' => 5, 'label' => 'Start-X-Position (px)'],
                ],
            ],
            'branding' => [
                'label' => 'Branding',
                'fields' => [
                    'discountCode'  => ['type' => 'string', 'default' => 'BURNERKING20', 'maxlen' => 32, 'label' => 'Rabattcode'],
                    'discountLevel' => ['type' => 'int',    'default' => 3, 'min' => 1, 'max' => 10, 'step' => 1, 'label' => 'Level bei dem Code erscheint'],
                ],
            ],
            'antiCheat' => [
                'label' => 'Anti-Cheat',
                'fields' => [
                    'rateLimitSessionPerMin' => ['type' => 'int', 'default' => 10, 'min' => 1, 'max' => 1000, 'step' => 1, 'label' => 'Sessions/Min pro IP'],
                    'rateLimitScorePerMin'   => ['type' => 'int', 'default' => 30, 'min' => 1, 'max' => 1000, 'step' => 1, 'label' => 'Score-Submits/Min pro IP'],
                    'minSessionDurationSec'  => ['type' => 'int', 'default' => 5,  'min' => 0, 'max' => 60,   'step' => 1, 'label' => 'Min. Spielzeit vor Submit (s)'],
                ],
            ],
            'advanced' => [
                'label' => 'Erweitert',
                'fields' => [
                    'configRawJson' => ['type' => 'textarea', 'default' => '', 'label' => 'Config-Override (JSON)', 'help' => 'Ueberschreibt einzelne Felder. Erwartet ein flaches Objekt mit Engine-Keys, z.B. {"gravity": 2.0, "hitboxBuffer": 5}.'],
                ],
            ],
        ];
    }

    /** @return array<string, int|float|string|bool> */
    public static function defaults(): array
    {
        $out = [];
        foreach (self::all() as $section) {
            foreach ($section['fields'] as $key => $spec) {
                $out[$key] = $spec['default'];
            }
        }
        return $out;
    }

    /** @return array<string, array<string, mixed>> */
    public static function fieldMap(): array
    {
        $out = [];
        foreach (self::all() as $section) {
            foreach ($section['fields'] as $key => $spec) {
                $out[$key] = $spec;
            }
        }
        return $out;
    }

    /**
     * Sanitize + Range-Clamp fuer einen einzelnen Wert anhand seiner Spec.
     */
    public static function sanitizeValue(mixed $value, array $spec): mixed
    {
        $type = (string) ($spec['type'] ?? 'string');

        return match ($type) {
            'int' => (int) self::clampNumber((int) $value, $spec),
            'float' => (float) self::clampNumber((float) $value, $spec),
            'bool' => (bool) $value,
            'url' => esc_url_raw((string) $value),
            'textarea' => (string) $value,
            default => self::sanitizeString((string) $value, $spec),
        };
    }

    /** @param array<string, mixed> $spec */
    private static function clampNumber(int|float $value, array $spec): int|float
    {
        if (isset($spec['min']) && $value < $spec['min']) {
            $value = $spec['min'];
        }
        if (isset($spec['max']) && $value > $spec['max']) {
            $value = $spec['max'];
        }
        return $value;
    }

    /** @param array<string, mixed> $spec */
    private static function sanitizeString(string $value, array $spec): string
    {
        $clean = sanitize_text_field($value);
        $maxlen = isset($spec['maxlen']) ? (int) $spec['maxlen'] : 255;
        return mb_substr($clean, 0, $maxlen);
    }
}
