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
    }

    public function addMenu(): void
    {
        $settings = new SettingsPage();
        $scoreboard = new ScoreboardPage();

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
}
