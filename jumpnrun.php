<?php
/**
 * Plugin Name:       Jump-n-Run by ADKRU
 * Plugin URI:        https://github.com/herbeckrobin/adkru-jumpnrun
 * Description:       Jump-and-Run-Spiel als Shortcode fuer WordPress. Von Robin Herbeck fuer ADKRU.
 * Version:           0.6.1
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

define('JUMPNRUN_VERSION', '0.6.1');
define('JUMPNRUN_FILE', __FILE__);
define('JUMPNRUN_DIR', plugin_dir_path(__FILE__));
define('JUMPNRUN_URL', plugin_dir_url(__FILE__));

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
    /** @var \YahnisElsts\PluginUpdateChecker\v5p6\Vcs\GitHubApi $vcsApi */
    $vcsApi = $updateChecker->getVcsApi();
    $vcsApi->enableReleaseAssets();
}

add_action('plugins_loaded', static function (): void {
    load_plugin_textdomain('jumpnrun', false, dirname(plugin_basename(__FILE__)) . '/languages');
});

if (class_exists('Jumpnrun\\Plugin')) {
    \Jumpnrun\Plugin::boot();
}
