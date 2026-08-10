<?php

declare(strict_types=1);

namespace Jumpnrun\Shortcode;

use Jumpnrun\Assets\AssetRepository;
use Jumpnrun\Embed\GameAttributes;
use Jumpnrun\Embed\GameRenderer;

/**
 * Bindet das Spiel per [jumpnrun] ein.
 *
 * Reiner Adapter: nimmt die Shortcode-Attribute entgegen und reicht sie an den
 * GameRenderer weiter. Die Attribut-Definitionen kommen aus GameAttributes,
 * damit Shortcode, Block und Elementor-Widget dieselben Werte kennen.
 */
final class GameShortcode
{
    public const TAG = 'jumpnrun';

    private GameRenderer $renderer;

    public function __construct(?GameRenderer $renderer = null)
    {
        $this->renderer = $renderer ?? new GameRenderer();
    }

    /** Registriert Shortcode und Preload-Hook. */
    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
        // Level-1-Hintergründe parallel zum JS-Bundle preloaden, das verkürzt
        // die wahrgenommene Ladezeit messbar wenn Bilder groß sind.
        // Das Enqueue hängt bewusst NICHT hier dran, es passiert beim Rendern.
        add_action('wp_head', [$this, 'maybePreloadHeroAssets'], 5);
    }

    /**
     * @param array<string, mixed>|string $atts
     */
    public function render(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        $atts = shortcode_atts(GameAttributes::shortcodeDefaults(), $atts, self::TAG);

        return $this->renderer->render($atts);
    }

    /**
     * Prüft ob die aktuelle Seite das Spiel einbindet.
     *
     * Ausschließlich für den head-Preload, der zwangsläufig vor dem Rendern
     * läuft und darum den Seiteninhalt durchsuchen muss. Deckt post_content
     * und Elementor ab, kann aber nicht jeden Page-Builder kennen. Der Preload
     * ist deshalb eine Optimierung, keine Voraussetzung: bleibt er aus, lädt
     * das Spiel trotzdem, nur der Ladescreen steht etwas länger.
     */
    private function pageUsesGame(): bool
    {
        global $post;
        if (!$post instanceof \WP_Post) {
            return false;
        }

        if (has_shortcode($post->post_content, self::TAG)) {
            return true;
        }

        $elementorData = get_post_meta($post->ID, '_elementor_data', true);
        if (!is_string($elementorData) || $elementorData === '') {
            return false;
        }

        // Nur auf [jumpnrun] und [jumpnrun ...] matchen, nicht auf
        // [jumpnrun_scoreboard]. Das native Elementor-Widget taucht hier
        // als widgetType auf, darum wird es zusätzlich geprüft.
        if (preg_match('/\[' . preg_quote(self::TAG, '/') . '[\s\]]/', $elementorData) === 1) {
            return true;
        }

        return str_contains($elementorData, '"widgetType":"jumpnrun_game"');
    }

    /**
     * Gibt `<link rel="preload" as="image">` Tags für Level-1-Backgrounds aus.
     * Browser lädt sie parallel zum JS-Bundle, der Spieler sieht den Loading-
     * Screen kürzer wenn Hintergründe groß sind (Adco-Anforderung: bis 12k px).
     */
    public function maybePreloadHeroAssets(): void
    {
        if (!$this->pageUsesGame()) {
            return;
        }

        $pools = AssetRepository::pools();
        $level1 = $pools['backgrounds'][1] ?? [];
        if (!is_array($level1) || $level1 === []) {
            return;
        }

        // Maximal 3 Tags ausgeben, mehr Preload-Hints konkurrieren um die
        // gleiche Bandbreite und blockieren wichtigere Ressourcen.
        $count = 0;
        foreach ($level1 as $item) {
            if ($count >= 3) {
                break;
            }
            $url = is_array($item) ? ($item['url'] ?? null) : null;
            if (!is_string($url) || $url === '') {
                continue;
            }
            printf(
                '<link rel="preload" as="image" href="%s" />' . "\n",
                esc_url($url)
            );
            $count++;
        }
    }
}
