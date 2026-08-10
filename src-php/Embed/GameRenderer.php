<?php

declare(strict_types=1);

namespace Jumpnrun\Embed;

use Jumpnrun\Api\RestController;
use Jumpnrun\Assets\AssetRepository;
use Jumpnrun\Config\ConfigService;

/**
 * Erzeugt Markup und Frontend-Config für das Spiel.
 *
 * Der eine Weg zum HTML, unabhängig davon, wie das Spiel eingebunden wurde.
 * Shortcode, Gutenberg-Block und Elementor-Widget sind dünne Adapter darüber
 * und liefern nur ihre Roh-Attribute an. Damit gibt es keine Variante, die
 * beim Ausbau einer Einbindung vergessen wird.
 */
final class GameRenderer
{
    /**
     * Lädt Client-Skript und CSS.
     *
     * Wird aus render() aufgerufen statt über wp_enqueue_scripts. Nur so
     * kommen die Assets bei JEDEM Einbindungsweg mit. Page-Builder legen den
     * Seiteninhalt in Postmeta ab (Elementor: _elementor_data), eine
     * has_shortcode()-Prüfung auf post_content greift dort ins Leere: das
     * Markup rendert, das Bundle fehlt, der Besucher sieht eine leere Fläche.
     * wp_enqueue_* im Shortcode-Callback ist zulässig, die Ausgabe landet
     * dann im Footer.
     */
    public function enqueueAssets(): void
    {
        $script = JUMPNRUN_URL . 'assets/game/client.js';
        $css = JUMPNRUN_URL . 'assets/game/client.css';

        wp_enqueue_script_module('jumpnrun-client', $script, [], JUMPNRUN_VERSION);
        if (file_exists(JUMPNRUN_DIR . 'assets/game/client.css')) {
            wp_enqueue_style('jumpnrun-client', $css, [], JUMPNRUN_VERSION);
        }
    }

    /**
     * Baut die Game-Config (Engine, Sprites, Asset-Pools, API) und gibt den
     * Root-Container samt Config-Bootstrap als HTML zurück.
     *
     * @param array<string, mixed> $attributes Roh-Attribute des Einbindungswegs
     */
    public function render(array $attributes = []): string
    {
        $this->enqueueAssets();

        // Admin-Einstellungen als Basis, Instanz-Attribute als Override.
        $engine = GameAttributes::applyToEngine(ConfigService::engineConfig(), $attributes);

        // Es gibt bewusst KEINE Default-Bild-URLs. Bilder kommen ausschließlich
        // aus drei Quellen:
        //   1. CPT-Pool jnr_background  (via AssetRepository)
        //   2. CPT-Pool jnr_obstacle    (via AssetRepository)
        //   3. Settings-Overrides       (Player, Coin, Plattform, Media-Picker im Admin)
        // Die Plugin-eigenen PNGs unter assets/sprites/ sind nur noch Quelle
        // für den Seeder, sie gehen nicht automatisch ans Frontend. Was der
        // Kunde nicht zugewiesen hat, zeigt der Renderer als Farbfläche
        // (FALLBACK-Map in canvas.ts) bzw. als Sky-Gradient beim Hintergrund.
        $images = [];
        foreach (ConfigService::spriteOverrides() as $key => $url) {
            $images[$key] = $url;
        }
        $assets = $this->buildAssetPools($images);

        $config = [
            'engine' => $engine,
            'api' => [
                'root' => esc_url_raw(rest_url(RestController::NAMESPACE . '/')),
                'nonce' => wp_create_nonce('wp_rest'),
            ],
            'images' => $images,
            'assets' => $assets,
            'scoreboard' => ConfigService::scoreboardConfig(),
            'viewport' => ConfigService::viewportConfig(),
        ];

        $json = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP);
        $width = (int) $engine['canvasWidth'];

        // Single-Column-Layout. Das Scoreboard wird nur im Game-Over-Overlay
        // angezeigt und vom Client selbst gebaut (siehe scoreboard.ts).
        return sprintf(
            '<div id="jumpnrun-root" style="max-width:%dpx;margin-inline:auto;"></div>' .
            '<script>window.JumpnrunConfig=%s;</script>',
            $width,
            $json
        );
    }

    /**
     * Maßstabsgetreuer Platzhalter für Editor-Ansichten.
     *
     * Im Elementor- und Gutenberg-Editor soll das Spiel nicht wirklich
     * starten: es würde im Editor-iframe mitlaufen, Tastatureingaben abfangen
     * und beim Bearbeiten stören.
     *
     * Der Platzhalter muss dafür aber denselben Platz einnehmen wie das
     * fertige Spiel, sonst lässt sich das Seitenlayout im Editor nicht
     * beurteilen. Er übernimmt deshalb Breite und Seitenverhältnis aus
     * derselben Config wie das Frontend und zeigt, wenn vorhanden, einen
     * echten Level-1-Hintergrund als Vorschaubild.
     *
     * @param array<string, mixed> $attributes Roh-Attribute des Einbindungswegs
     */
    public function renderEditorPlaceholder(array $attributes = []): string
    {
        $engine = GameAttributes::applyToEngine(ConfigService::engineConfig(), $attributes);

        $width = max(1, (int) $engine['canvasWidth']);
        $height = max(1, (int) $engine['canvasHeight']);

        // Gleiche Box wie im Frontend: max-width plus zentriert.
        $box = sprintf(
            'max-width:%dpx;margin-inline:auto;aspect-ratio:%d / %d;',
            $width,
            $width,
            $height
        );

        $preview = $this->firstBackgroundUrl();
        $surface = $preview !== null
            ? sprintf(
                'background-image:url(%s);background-size:cover;background-position:center;',
                esc_url($preview)
            )
            : 'background:repeating-linear-gradient(45deg,rgba(127,127,127,.1) 0 12px,transparent 12px 24px);';

        // Der Text sitzt in einer eigenen dunklen Box statt direkt auf dem Bild.
        // Wie hell der Hintergrund ist, entscheidet der Kunde in der Mediathek,
        // ein Overlay über die ganze Fläche wäre also mal zu schwach und mal zu
        // stark. So bleibt die Beschriftung immer lesbar und das Bild erkennbar.
        $label = 'display:inline-flex;flex-direction:column;gap:.2rem;'
            . 'padding:.75rem 1.1rem;border-radius:8px;'
            . 'background:rgba(17,17,17,.78);color:#fff;text-align:center;'
            . 'font-family:system-ui,sans-serif;line-height:1.35;';

        return sprintf(
            '<div class="jumpnrun-editor-placeholder" style="%s%s%s">'
            . '<span style="%s">'
            . '<strong style="font-size:1.05rem;">%s</strong>'
            . '<span style="font-size:.85rem;opacity:.9;">%s</span>'
            . '<span style="font-size:.78rem;opacity:.75;">%s</span>'
            . '</span>'
            . '</div>',
            $box,
            $surface,
            'display:flex;align-items:center;justify-content:center;'
            . 'padding:1rem;box-sizing:border-box;'
            . 'border:1px dashed rgba(127,127,127,.6);border-radius:6px;',
            $label,
            esc_html__('Jump and Run', 'jumpnrun'),
            esc_html(
                sprintf(
                    /* translators: 1: Breite in Pixel, 2: Höhe in Pixel */
                    __('Spielfeld %1$d x %2$d Pixel', 'jumpnrun'),
                    $width,
                    $height
                )
            ),
            esc_html__('Startet erst in der Vorschau, nicht im Editor.', 'jumpnrun')
        );
    }

    /**
     * Erste Level-1-Hintergrund-URL für die Editor-Vorschau, falls gepflegt.
     *
     * Bewusst nur Level 1: das ist der Hintergrund, den der Besucher als
     * erstes sieht, also der ehrlichste Eindruck fürs Layout.
     */
    private function firstBackgroundUrl(): ?string
    {
        $level1 = AssetRepository::pools()['backgrounds'][1] ?? [];
        if (!is_array($level1)) {
            return null;
        }

        foreach ($level1 as $item) {
            $url = is_array($item) ? ($item['url'] ?? null) : null;
            if (is_string($url) && $url !== '') {
                return $url;
            }
        }

        return null;
    }

    /**
     * Nimmt die im Admin gepflegten Asset-CPTs, verteilt deren Bild-URLs in die
     * `$images`-Map mit eindeutigen Keys und liefert die Engine-kompatiblen Pools
     * mit `imageKey`-Referenzen zurück.
     *
     * @param array<string, string> $images Pass-by-reference: die Map wird um CPT-Einträge erweitert
     * @return array{backgrounds: \stdClass|array<string, list<array{imageKey:string,weight:int}>>, obstacles: list<array{imageKey:string,width:int,height:int,minLevel:int,weight:int}>, platforms: list<array{imageKey:string,width:int,height:int,weight:int}>}
     */
    private function buildAssetPools(array &$images): array
    {
        $pools = AssetRepository::pools();

        $backgrounds = [];
        foreach ($pools['backgrounds'] as $level => $items) {
            $list = [];
            foreach ($items as $idx => $item) {
                $key = sprintf('bg-cpt-%d-%d', (int) $level, $idx);
                $images[$key] = $item['url'];
                $list[] = [
                    'imageKey' => $key,
                    'weight' => (int) $item['weight'],
                ];
            }
            $backgrounds[(string) $level] = $list;
        }

        $obstacles = [];
        foreach ($pools['obstacles'] as $idx => $item) {
            $key = sprintf('obstacle-cpt-%d', $idx);
            $images[$key] = $item['url'];
            $obstacles[] = [
                'imageKey' => $key,
                'width' => (int) $item['width'],
                'height' => (int) $item['height'],
                'minLevel' => (int) $item['minLevel'],
                'weight' => (int) $item['weight'],
            ];
        }

        $platforms = [];
        foreach ($pools['platforms'] as $idx => $item) {
            $key = sprintf('platform-cpt-%d', $idx);
            $images[$key] = $item['url'];
            $platforms[] = [
                'imageKey' => $key,
                'width' => (int) $item['width'],
                'height' => (int) $item['height'],
                'weight' => (int) $item['weight'],
            ];
        }

        return [
            // Leerer Pool wird stdClass, damit JS-seitig ein Objekt bleibt
            // statt zu einem Array zu degenerieren.
            'backgrounds' => $backgrounds === [] ? new \stdClass() : $backgrounds,
            'obstacles' => $obstacles,
            'platforms' => $platforms,
        ];
    }
}
