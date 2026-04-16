<?php
/**
 * Plugin Name:       Jump-n-Run by ADKRU
 * Plugin URI:        https://github.com/herbeckrobin/adkru-jumpnrun
 * Description:       Jump-and-Run-Spiel als Shortcode fuer WordPress. Von Robin Herbeck fuer ADKRU.
 * Version:           0.0.1
 * Requires at least: 6.4
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

$autoload = JUMPNRUN_DIR . 'vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// GitHub-Updater: Tag-Pushes werden automatisch als WP-Plugin-Update erkannt.
// Aktiviert sich nur wenn der Plugin-Update-Checker via Composer installiert wurde.
if (class_exists('YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory')) {
    $updateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
        'https://github.com/herbeckrobin/adkru-jumpnrun/',
        __FILE__,
        'jumpnrun'
    );
    $updateChecker->setBranch('main');
    $updateChecker->getVcsApi()->enableReleaseAssets();
}

// Platzhalter — Plugin-Bootstrap wird in Phase 6+ umgesetzt.
add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('jumpnrun', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

// Shortcode-Platzhalter, damit das Plugin schon jetzt ohne Fehler aktivierbar ist.
add_shortcode('jumpnrun', static function (): string {
    return '<div class="jumpnrun-placeholder">' . esc_html__('Jump-n-Run wird geladen …', 'jumpnrun') . '</div>';
});

add_shortcode('jumpnrun_scoreboard', static function (): string {
    return '<div class="jumpnrun-scoreboard-placeholder">' . esc_html__('Highscore kommt bald.', 'jumpnrun') . '</div>';
});

// Activation-Hook: Tabellen-Setup kommt in Phase 8.
register_activation_hook(__FILE__, static function (): void {
    // dbDelta kommt hier rein.
});

register_deactivation_hook(__FILE__, static function (): void {
    // Optionen-Cleanup folgt in Phase 7.
});
