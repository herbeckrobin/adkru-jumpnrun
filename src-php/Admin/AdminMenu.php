<?php

declare(strict_types=1);

namespace Jumpnrun\Admin;

final class AdminMenu
{
    public const SLUG = 'jumpnrun';
    public const SCOREBOARD_SLUG = 'jumpnrun_scoreboard';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu']);
        add_action('admin_init', [new SettingsPage(), 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
    }

    public function addMenu(): void
    {
        $settings = new SettingsPage();
        $scoreboard = new ScoreboardPage();
        $assets = new AssetsPage();

        // Top-Level-Page = Settings. WP fuegt automatisch einen ersten Submenu-Eintrag
        // mit dem Menu-Title hinzu — wir benennen den unten zu "Einstellungen" um.
        add_menu_page(
            'Jump-n-Run',
            'Jump-n-Run',
            'manage_options',
            self::SLUG,
            [$settings, 'render'],
            'dashicons-games',
            58
        );

        // Reihenfolge: Einstellungen → Spiel-Assets → Highscores.
        // Hintergruende/Hindernisse haben keine eigenen Submenu-Eintraege mehr —
        // sie sind als Tabs in Spiel-Assets gebuendelt.
        add_submenu_page(
            self::SLUG,
            'Spiel-Assets',
            'Spiel-Assets',
            'edit_posts',
            AssetsPage::SLUG,
            [$assets, 'render']
        );
        add_submenu_page(
            self::SLUG,
            'Highscores',
            'Highscores',
            'manage_options',
            self::SCOREBOARD_SLUG,
            [$scoreboard, 'render']
        );

        // Auto-Submenu-Titel "Jump-n-Run" → "Einstellungen" umbenennen.
        global $submenu;
        if (isset($submenu[self::SLUG][0])) {
            $submenu[self::SLUG][0][0] = 'Einstellungen';
        }
    }

    /**
     * Laed den Media-Picker auf der Settings-Seite UND auf der Spiel-Assets-Seite
     * (Sprites-Tab braucht ihn) — auf den CPT-Editoren laed WordPress den
     * Media-Frame ohnehin selbst.
     */
    public function enqueueAssets(string $hookSuffix): void
    {
        $isPluginPage =
            $hookSuffix === 'toplevel_page_' . self::SLUG
            || str_ends_with($hookSuffix, '_page_' . AssetsPage::SLUG);
        if (!$isPluginPage) {
            return;
        }
        wp_enqueue_media();
        wp_enqueue_script(
            'jumpnrun-media-picker',
            JUMPNRUN_URL . 'assets/admin/media-picker.js',
            ['jquery'],
            JUMPNRUN_VERSION,
            true
        );
    }
}
