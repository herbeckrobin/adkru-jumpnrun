<?php
/**
 * @var array<string, array{label:string, fields: array<string, array<string, mixed>>}> $sections
 * @var array<string, mixed> $current
 * @var string $optionKey
 * @var string $optionGroup
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

use Jumpnrun\Admin\SettingsPage;

$activeTab = isset($_GET['tab']) ? sanitize_key((string) $_GET['tab']) : (string) array_key_first($sections);
if (!isset($sections[$activeTab])) {
    $activeTab = (string) array_key_first($sections);
}
?>
<div class="wrap jumpnrun-settings">
    <h1><?php echo esc_html__('Jump-n-Run · Einstellungen', 'jumpnrun'); ?></h1>

    <?php settings_errors($optionKey); ?>

    <nav class="nav-tab-wrapper">
        <?php foreach ($sections as $slug => $section) : ?>
            <a href="<?php echo esc_url(add_query_arg('tab', $slug)); ?>"
               class="nav-tab <?php echo $slug === $activeTab ? 'nav-tab-active' : ''; ?>">
                <?php echo esc_html($section['label']); ?>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="post" action="options.php" class="jumpnrun-settings-form">
        <?php settings_fields($optionGroup); ?>

        <?php foreach ($sections as $slug => $section) : ?>
            <div class="jnr-section <?php echo $slug === $activeTab ? '' : 'jnr-section-hidden'; ?>"
                 data-section="<?php echo esc_attr($slug); ?>">
                <h2><?php echo esc_html($section['label']); ?></h2>
                <table class="form-table" role="presentation">
                    <tbody>
                    <?php foreach ($section['fields'] as $key => $spec) : ?>
                        <tr>
                            <th scope="row">
                                <?php echo esc_html((string) $spec['label']); ?>
                            </th>
                            <td>
                                <?php SettingsPage::renderField($key, $spec, $current); ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endforeach; ?>

        <?php submit_button(); ?>
    </form>
</div>

<style>
    .jumpnrun-settings .jnr-section-hidden { display: none; }
    .jumpnrun-settings .jnr-label { display: none; }
    .jumpnrun-settings .nav-tab-wrapper { margin-bottom: 1em; }
</style>
