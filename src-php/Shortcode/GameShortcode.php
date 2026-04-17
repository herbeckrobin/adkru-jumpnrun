<?php

declare(strict_types=1);

namespace Jumpnrun\Shortcode;

use Jumpnrun\Api\RestController;
use Jumpnrun\Config\ConfigService;

final class GameShortcode
{
    public const TAG = 'jumpnrun';

    public function register(): void
    {
        add_shortcode(self::TAG, [$this, 'render']);
        add_action('wp_enqueue_scripts', [$this, 'maybeEnqueue']);
    }

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
     * @param array<string, mixed>|string $atts
     */
    public function render(array|string $atts = []): string
    {
        if (!is_array($atts)) {
            $atts = [];
        }

        $defaults = ConfigService::gameDefaults();

        $atts = shortcode_atts([
            'width' => 960,
            'height' => 540,
            'discount_code' => $defaults['discountCode'],
        ], $atts, self::TAG);

        $sprites = JUMPNRUN_URL . 'assets/sprites/';
        $config = [
            'width' => (int) $atts['width'],
            'height' => (int) $atts['height'],
            'discountCode' => (string) $atts['discount_code'],
            'api' => [
                'root' => esc_url_raw(rest_url(RestController::NAMESPACE . '/')),
                'nonce' => wp_create_nonce('wp_rest'),
            ],
            'images' => $this->spriteMap($sprites),
        ];

        $json = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP);
        $width = (int) $atts['width'];

        return sprintf(
            '<div id="jumpnrun-root" style="max-width:%dpx;margin-inline:auto;"></div>' .
            '<script>window.JumpnrunConfig=%s;</script>',
            $width,
            $json
        );
    }

    /** @return array<string, string> */
    private function spriteMap(string $base): array
    {
        $keys = [
            'bg-0', 'bg-1', 'bg-2', 'bg-3', 'bg-4',
            'bg-5', 'bg-6', 'bg-7', 'bg-8', 'bg-9',
            'player-idle', 'player-jump',
            'obstacle-0', 'obstacle-1', 'obstacle-2',
            'coin',
            'platform-0', 'platform-1',
        ];

        $map = [];
        foreach ($keys as $key) {
            $map[$key] = $base . $key . '.png';
        }
        return $map;
    }
}
