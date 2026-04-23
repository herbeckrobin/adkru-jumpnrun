<?php

declare(strict_types=1);

namespace Jumpnrun\Db;

/**
 * Schema-Installation per dbDelta. Idempotent: kann bei jedem Aktivieren
 * erneut laufen, aendert Tabellen nur bei Schema-Unterschieden.
 */
final class Schema
{
    public const VERSION = '1';
    public const HIGHSCORES = 'jumpnrun_highscores';
    public const SESSIONS = 'jumpnrun_sessions';

    /**
     * Laeuft einmal pro Request bei plugins_loaded und ruft install() nur dann,
     * wenn die Schema-Version nicht passt. So kommen Tabellen auch nach
     * GitHub-Auto-Updates automatisch mit — ohne dass Tomy das Plugin
     * deaktivieren und reaktivieren muss.
     */
    public static function maybeUpgrade(): void
    {
        if (get_option('jumpnrun_schema_version') !== self::VERSION) {
            self::install();
        }
    }

    /** Erstellt oder aktualisiert die Plugin-Tabellen via dbDelta. */
    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $highscores = $wpdb->prefix . self::HIGHSCORES;
        $sessions = $wpdb->prefix . self::SESSIONS;

        dbDelta(
            "CREATE TABLE {$highscores} (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                name VARCHAR(32) NOT NULL,
                score INT UNSIGNED NOT NULL,
                level TINYINT UNSIGNED NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                UNIQUE KEY name (name),
                KEY score_idx (score)
            ) {$charset};"
        );

        dbDelta(
            "CREATE TABLE {$sessions} (
                id CHAR(36) NOT NULL,
                state VARCHAR(16) NOT NULL DEFAULT 'active',
                ip_hash CHAR(64) NOT NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                PRIMARY KEY  (id),
                KEY state_idx (state)
            ) {$charset};"
        );

        update_option('jumpnrun_schema_version', self::VERSION);
    }

    /** Voller Tabellenname der Highscores-Tabelle inklusive WP-Prefix. */
    public static function highscoresTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::HIGHSCORES;
    }

    /** Voller Tabellenname der Sessions-Tabelle inklusive WP-Prefix. */
    public static function sessionsTable(): string
    {
        global $wpdb;
        return $wpdb->prefix . self::SESSIONS;
    }
}
