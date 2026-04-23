<?php

declare(strict_types=1);

namespace Jumpnrun\Shortcode;

use Jumpnrun\Api\RestController;
use Jumpnrun\Assets\AssetRepository;
use Jumpnrun\Config\ConfigService;

/** Rendert den Spiel-Canvas samt Config-Bootstrap fuer [jumpnrun]. */
final class GameShortcode
{
    public const TAG = 'jumpnrun';

    /** Registriert Shortcode und Enqueue-Hook. */
    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

    /** Laed Client-Skript und CSS nur auf Seiten die den Shortcode enthalten. */
    public function maybeEnqueue(): void
    {
        global $post;
        if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, self::TAG)) {
            return;
        }

        $script = JUMPNRUN_URL . 'assets/game/client.js';
        $css = JUMPNRUN_URL . 'assets/game/client.css';

        wp_enqueue_script_module('jumpnrun-client', $script, [], JUMPNRUN_VERSION);
        if (file_exists(JUMPNRUN_DIR . 'assets/game/client.css')) {
            wp_enqueue_style('jumpnrun-client', $css, [], JUMPNRUN_VERSION);
        }
    }

    /**
     * Baut die Game-Config (Engine, Sprites, Asset-Pools, API) und gibt den Root-Container als HTML zurueck.
     *
     * @param array<string, mixed>|string $atts
     */
    public function render(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        // Admin-Settings ins Engine-Objekt, dann Shortcode-Attrs als Instance-Override.
        $engine = ConfigService::engineConfig();

        $atts = shortcode_atts([
            'width' => null,
            'height' => null,
            'discount_code' => null,
        ], $atts, self::TAG);

        if ($atts['width'] !== null) {
            $engine['canvasWidth'] = (int) $atts['width'];
        }
        if ($atts['height'] !== null) {
            $engine['canvasHeight'] = (int) $atts['height'];
        }
        if ($atts['discount_code'] !== null) {
            $engine['discountCode'] = (string) $atts['discount_code'];
        }

        $sprites = JUMPNRUN_URL . 'assets/sprites/';
        $scoreboard = ConfigService::scoreboardConfig();
        $images = $this->spriteMap($sprites);
        // Per Media-Picker zugewiesene Sprites ueberschreiben die Defaults.
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
            'scoreboard' => $scoreboard,
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
     * Liefert grundsaetzlich KEINE Default-Bild-URLs mehr. Bilder kommen
     * ausschliesslich aus drei Quellen:
     *
     *   1. CPT-Pool jnr_background  (via AssetRepository)
     *   2. CPT-Pool jnr_obstacle    (via AssetRepository)
     *   3. Settings-Overrides       (Player/Coin/Plattform — Media-Picker im Admin)
     *
     * Was der Kunde nicht in der Mediathek zugewiesen hat, zeigt der Renderer
     * als Solid-Color-Box (FALLBACK-Map in canvas.ts) bzw. den Sky-Gradient
     * fuer den Hintergrund. Konsistente Regel: keine Zuweisung → Farbflaeche.
     *
     * @return array<string, string>
     */
    private function spriteMap(string $base): array
    {
        // Plugin-Default-PNGs werden nicht mehr automatisch ans Frontend
        // ausgeliefert — sie sind nur noch Source fuer den Seeder.
        return [];
    }

    /**
     * Nimmt die im Admin gepflegten Asset-CPTs, verteilt deren Bild-URLs in die
     * `$images`-Map mit eindeutigen Keys und liefert die Engine-kompatiblen Pools
     * mit `imageKey`-Referenzen zurueck.
     *
     * @param array<string, string> $images Pass-by-reference: die Map wird um CPT-Eintraege erweitert
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
            // Leerer Pool → stdClass damit JS-seitig ein Objekt bleibt statt zu einem Array zu degenerieren.
            'backgrounds' => $backgrounds === [] ? new \stdClass() : $backgrounds,
            'obstacles' => $obstacles,
            'platforms' => $platforms,
        ];
    }
}
