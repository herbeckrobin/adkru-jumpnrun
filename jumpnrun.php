<?php
/**
 * Plugin Name:       Jump-n-Run by ADKRU
 * Plugin URI:        https://github.com/herbeckrobin/adkru-jumpnrun
 * Description:       Jump-and-Run-Spiel als Shortcode fuer WordPress. Von Robin Herbeck fuer ADKRU.
 * Version:           0.0.1
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Author:            Robin Herbeck
 * Author URI:        https://github.com/herbeckrobin
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       jumpnrun
 * Domain Path:       /languages
 * Update URI:        https://github.com/herbeckrobin/adkru-jumpnrun
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('JUMPNRUN_VERSION', '0.0.1');
define('JUMPNRUN_FILE', __FILE__);
define('JUMPNRUN_DIR', plugin_dir_path(__FILE__));
define('JUMPNRUN_URL', plugin_dir_url(__FILE__));

// Composer autoload (Plugin-Update-Checker + PSR-4 classes)
$autoload = JUMPNRUN_DIR . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// GitHub-Updater: Tag-Pushes werden automatisch als WP-Plugin-Update erkannt.
if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/herbeckrobin/adkru-jumpnrun/',
        __FILE__,
        'jumpnrun'
    );
    $updateChecker->setBranch('main');
    $updateChecker->getVcsApi()->enableReleaseAssets();
}

// Text domain
add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('jumpnrun', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// ── Assets ────────────────────────────────────────────────────────────────

/**
 * Enqueue game script + styles only on pages that use the [jumpnrun] shortcode.
 * Uses wp_enqueue_script_module (WP 6.5+) for native ES-module loading.
 */
add_action('wp_enqueue_scripts', static function (): void {
    global $post;
    if (!is_a($post, 'WP_Post') || !has_shortcode($post->post_content, 'jumpnrun')) {
        return;
    }

    $script_url = JUMPNRUN_URL . 'assets/game/client.mjs';
    $css_url    = JUMPNRUN_URL . 'assets/game/client.css';

    // wp_enqueue_script_module loads <script type="module"> natively (WP 6.5+)
    wp_enqueue_script_module('jumpnrun-client', $script_url, [], JUMPNRUN_VERSION);

    // CSS is enqueued separately so it loads even without JS
    if (file_exists(JUMPNRUN_DIR . 'assets/game/client.css')) {
        wp_enqueue_style('jumpnrun-client', $css_url, [], JUMPNRUN_VERSION);
    }
});

// ── Shortcode [jumpnrun] ──────────────────────────────────────────────────

/**
 * Usage: [jumpnrun width="960" height="540" discount_code="BURNERKING20"]
 *
 * The JS bootstrap() reads window.JumpnrunConfig and mounts the game
 * inside #jumpnrun-root automatically on DOMContentLoaded.
 */
add_shortcode('jumpnrun', static function (array|string $atts = []): string {
    if (!is_array($atts)) {
        $atts = [];
    }

    $atts = shortcode_atts([
        'width'         => 960,
        'height'        => 540,
        'discount_code' => 'BURNERKING20',
    ], $atts, 'jumpnrun');

    $sprites = JUMPNRUN_URL . 'assets/sprites/';
    $config  = [
        'width'        => (int) $atts['width'],
        'height'       => (int) $atts['height'],
        'discountCode' => (string) $atts['discount_code'],
        'images'       => [
            'bg-0'        => $sprites . 'bg-0.png',
            'bg-1'        => $sprites . 'bg-1.png',
            'bg-2'        => $sprites . 'bg-2.png',
            'bg-3'        => $sprites . 'bg-3.png',
            'bg-4'        => $sprites . 'bg-4.png',
            'bg-5'        => $sprites . 'bg-5.png',
            'bg-6'        => $sprites . 'bg-6.png',
            'bg-7'        => $sprites . 'bg-7.png',
            'bg-8'        => $sprites . 'bg-8.png',
            'bg-9'        => $sprites . 'bg-9.png',
            'player-idle' => $sprites . 'player-idle.png',
            'player-jump' => $sprites . 'player-jump.png',
            'obstacle-0'  => $sprites . 'obstacle-0.png',
            'obstacle-1'  => $sprites . 'obstacle-1.png',
            'obstacle-2'  => $sprites . 'obstacle-2.png',
            'coin'        => $sprites . 'coin.png',
            'platform-0'  => $sprites . 'platform-0.png',
            'platform-1'  => $sprites . 'platform-1.png',
        ],
    ];

    $json   = wp_json_encode($config, JSON_HEX_TAG | JSON_HEX_AMP);
    $width  = (int) $atts['width'];

    return sprintf(
        '<div id="jumpnrun-root" style="max-width:%dpx;margin-inline:auto;"></div>' .
        '<script>window.JumpnrunConfig=%s;</script>',
        $width,
        $json
    );
});

// ── Scoreboard shortcode (placeholder, REST endpoint in next phase) ───────

add_shortcode('jumpnrun_scoreboard', static function (): string {
    return '<div class="jumpnrun-scoreboard-placeholder">'
        . esc_html__('Highscore kommt bald.', 'jumpnrun')
        . '</div>';
});

// ── Activation / Deactivation hooks ──────────────────────────────────────

register_activation_hook(__FILE__, static function (): void {
    // DB table setup (highscores) will be added in Phase 8.
});

register_deactivation_hook(__FILE__, static function (): void {
    // Cleanup follows in Phase 7.
});
