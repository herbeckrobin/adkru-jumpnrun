<?php

declare(strict_types=1);

namespace Jumpnrun\Embed;

/**
 * Single Source für die Instanz-Attribute des Spiels.
 *
 * Aus diesem Schema leiten sich alle Einbindungswege ab: die Defaults für
 * `shortcode_atts()`, die Attribute des Gutenberg-Blocks und die Controls des
 * Elementor-Widgets. Ein neues Attribut wird genau hier ergänzt, nicht an drei
 * Stellen parallel.
 *
 * Nicht zu verwechseln mit ConfigSchema: das hält die globalen Admin-
 * Einstellungen. Hier geht es nur um Werte, die pro eingebundener Instanz
 * abweichen dürfen.
 */
final class GameAttributes
{
    /**
     * Attribut-Definitionen.
     *
     * - `type`   steuert das Casting und später den Control-Typ im Editor
     * - `engine` ist der Schlüssel im Engine-Config, den das Attribut überschreibt
     * - `label`  wird im Block- und Elementor-Panel angezeigt
     *
     * @return array<string, array{type:string, engine:string, label:string, description:string}>
     */
    public static function schema(): array
    {
        return [
            'width' => [
                'type' => 'number',
                'engine' => 'canvasWidth',
                'label' => __('Breite', 'jumpnrun'),
                'description' => __('Maximale Breite des Spielfelds in Pixel. Leer lassen für den Wert aus den Einstellungen.', 'jumpnrun'),
            ],
            'height' => [
                'type' => 'number',
                'engine' => 'canvasHeight',
                'label' => __('Höhe', 'jumpnrun'),
                'description' => __('Höhe des Spielfelds in Pixel. Leer lassen für den Wert aus den Einstellungen.', 'jumpnrun'),
            ],
            'discount_code' => [
                'type' => 'string',
                'engine' => 'discountCode',
                'label' => __('Rabattcode', 'jumpnrun'),
                'description' => __('Überschreibt den Code aus den Einstellungen, etwa für eine einzelne Kampagnenseite.', 'jumpnrun'),
            ],
        ];
    }

    /**
     * Defaults für `shortcode_atts()`. Durchgängig null, weil ein nicht
     * gesetztes Attribut den Admin-Wert stehen lassen soll.
     *
     * @return array<string, null>
     */
    public static function shortcodeDefaults(): array
    {
        return array_fill_keys(array_keys(self::schema()), null);
    }

    /**
     * Bringt Roh-Attribute aus beliebiger Quelle auf typisierte Werte.
     *
     * Unbekannte Schlüssel fallen raus. Ein Wert gilt als "nicht gesetzt",
     * wenn er null oder ein leerer String ist: Elementor liefert für leere
     * Felder einen leeren String, und der darf die Admin-Einstellung nicht
     * mit 0 überschreiben.
     *
     * @param  array<string, mixed> $raw
     * @return array<string, int|string> nur tatsächlich gesetzte Werte
     */
    public static function normalize(array $raw): array
    {
        $normalized = [];

        foreach (self::schema() as $key => $definition) {
            if (!array_key_exists($key, $raw)) {
                continue;
            }

            $value = $raw[$key];
            if ($value === null || $value === '') {
                continue;
            }

            if ($definition['type'] === 'number') {
                if (!is_numeric($value)) {
                    continue;
                }
                $normalized[$key] = (int) $value;
                continue;
            }

            $normalized[$key] = (string) $value;
        }

        return $normalized;
    }

    /**
     * Legt die Instanz-Attribute über die Engine-Config aus den Admin-Einstellungen.
     *
     * @param  array<string, mixed> $engine
     * @param  array<string, mixed> $rawAttributes
     * @return array<string, mixed>
     */
    public static function applyToEngine(array $engine, array $rawAttributes): array
    {
        $schema = self::schema();

        foreach (self::normalize($rawAttributes) as $key => $value) {
            $engine[$schema[$key]['engine']] = $value;
        }

        return $engine;
    }
}
