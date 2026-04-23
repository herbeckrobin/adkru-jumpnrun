<?php

declare(strict_types=1);

namespace Jumpnrun\Assets;

use WP_Query;

/**
 * Befüllt die Asset-Pools mit den im Plugin mitgelieferten Default-Sprites.
 *
 * Architektur: KEIN Auto-Seed beim Plugin-Activate oder admin_init mehr.
 * Stattdessen ein expliziter Button auf der Settings-Seite, der admin-post.php
 * triggert und einmalig die 10 Backgrounds + 3 Obstacles in die Mediathek
 * importiert und die zugehörigen CPT-Posts erstellt.
 *
 * Vorteil: keine ungewollten Mediathek-Einträge bei stillem Auto-Seed,
 * Tomy/Kunde entscheidet bewusst. Auch nützlich als „Defaults wiederherstellen".
 *
 * Idempotent über das Options-Flag `jumpnrun_assets_seeded`. Doppelt-Seed
 * (z.B. bei zweimaligem Klick) wird durch den hasAnyAssets()-Check verhindert.
 */
final class AssetSeeder
{
    private const FLAG = 'jumpnrun_assets_seeded';
    public const ACTION = 'jumpnrun_seed_defaults';

    /**
     * Sprechende Namen + Bildinhalte für die Default-Backgrounds. Robin/Tomy
     * können in der Mediathek umbenennen, das hier ist nur der Initial-Seed.
     *
     * @var array<int, array{slug:string, title:string}>
     */
    private const BACKGROUNDS = [
        ['slug' => 'bg-0', 'title' => 'Büro'],
        ['slug' => 'bg-1', 'title' => 'Meetingraum'],
        ['slug' => 'bg-2', 'title' => 'Druckerei'],
        ['slug' => 'bg-3', 'title' => 'Pausenküche'],
        ['slug' => 'bg-4', 'title' => 'Druckhalle'],
        ['slug' => 'bg-5', 'title' => 'Stadt'],
        ['slug' => 'bg-6', 'title' => 'Skigebiet'],
        ['slug' => 'bg-7', 'title' => 'Waldlichtung'],
        ['slug' => 'bg-8', 'title' => 'Bahnsteig'],
        ['slug' => 'bg-9', 'title' => 'Stadtpark'],
    ];

    /** @var array<int, array{slug:string, title:string}> */
    private const OBSTACLES = [
        ['slug' => 'obstacle-0', 'title' => 'Monitor'],
        ['slug' => 'obstacle-1', 'title' => 'Aktenstapel'],
        ['slug' => 'obstacle-2', 'title' => 'Wasserspender'],
    ];

    /** @var array<int, array{slug:string, title:string}> */
    private const PLATFORMS = [
        ['slug' => 'platform-0', 'title' => 'Plattform Holz'],
        ['slug' => 'platform-1', 'title' => 'Plattform Stein'],
    ];

    /**
     * Sprites die NICHT als CPT laufen, sondern als Settings-Attachment-IDs
     * (Player + Coin). Plattformen liefen frueher hier — sind ab v0.4 ein
     * eigener CPT-Pool. Beim Seed werden sie in die Mediathek importiert
     * und die zugehoerigen Settings-Felder gefuellt.
     *
     * @var array<int, array{slug:string, title:string, settingKey:string}>
     */
    private const SPRITES = [
        ['slug' => 'player-idle', 'title' => 'Spielfigur (Moving)', 'settingKey' => 'playerIdleSprite'],
        ['slug' => 'player-jump', 'title' => 'Spielfigur (Sprung)', 'settingKey' => 'playerJumpSprite'],
        ['slug' => 'coin', 'title' => 'Coin', 'settingKey' => 'coinSprite'],
    ];

    /** Haengt den Form-Submit-Handler fuer den Seed-Button ein. */
    public function register(): void
    {
        add_action('admin_post_' . self::ACTION, [$this, 'handleAdminPost']);
    }

    /** True wenn der Seed schon mindestens einmal durchgelaufen ist. */
    public static function isSeeded(): bool
    {
        return get_option(self::FLAG) === '1';
    }

    /**
     * Form-Submit von der Settings-Seite. Nonce + Capability prüfen,
     * Seed laufen lassen, mit Status zurück redirecten.
     */
    /** Verarbeitet den Seed-Button, redirected mit Ergebnis-Parameter zurueck. */
    public function handleAdminPost(): void
    {
        check_admin_referer(self::ACTION);
        if (!current_user_can('upload_files') || !current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Jump-n-Run', ['response' => 403]);
        }

        $created = $this->doSeed();
        $redirect = wp_get_referer();
        if (!is_string($redirect) || $redirect === '') {
            $redirect = admin_url('admin.php?page=jumpnrun_assets');
        }
        wp_safe_redirect(add_query_arg('jnr_seeded', $created, $redirect));
        exit;
    }

    /**
     * Importiert (falls noch nicht geschehen) die Default-Sprites in die
     * Mediathek und legt CPT-Posts mit Featured-Image-Verknüpfung an.
     *
     * @return int Anzahl tatsächlich neu angelegter CPT-Posts (0 wenn bereits geseedet).
     */
    /** Fuehrt den eigentlichen Import von Default-Sprites und CPT-Posts aus. */
    public function doSeed(): int
    {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';

        $created = 0;

        // CPT-Pools (Backgrounds + Obstacles + Plattformen) nur seeden wenn
        // der Pool wirklich leer ist — verhindert Duplikate bei wiederholtem Klick.
        if (!$this->hasAnyAssets()) {
            foreach (self::BACKGROUNDS as $idx => $entry) {
                if ($this->seedBackground($entry['slug'], $entry['title'], $idx + 1)) {
                    $created++;
                }
            }
            foreach (self::OBSTACLES as $entry) {
                if ($this->seedObstacle($entry['slug'], $entry['title'])) {
                    $created++;
                }
            }
            foreach (self::PLATFORMS as $entry) {
                if ($this->seedPlatform($entry['slug'], $entry['title'])) {
                    $created++;
                }
            }
        }

        // Sprites werden IMMER geprüft — sie haben pro Slot ihre eigene
        // Idempotenz (Skip wenn Setting bereits eine Attachment-ID hat).
        // Greift auch fuer Bestandsinstallationen die noch ohne Sprite-Settings
        // geseedet wurden.
        $created += $this->seedSprites();

        update_option(self::FLAG, '1');
        AssetRepository::clearCache();
        return $created;
    }

    /**
     * Importiert die Player-/Coin-/Platform-Sprites in die Mediathek und
     * speichert deren Attachment-IDs in den jumpnrun_config-Settings, damit
     * der Sprites-Tab im Admin direkt die aktuellen Vorschauen zeigt.
     */
    private function seedSprites(): int
    {
        $settings = get_option(\Jumpnrun\Config\ConfigService::OPTION_KEY, []);
        if (!is_array($settings)) {
            $settings = [];
        }
        $created = 0;
        foreach (self::SPRITES as $entry) {
            // Wenn bereits ein Attachment im Setting steht, ueberspringen.
            if (!empty($settings[$entry['settingKey']])) {
                continue;
            }
            $attachmentId = $this->importSprite($entry['slug'] . '.png', $entry['title']);
            if ($attachmentId > 0) {
                $settings[$entry['settingKey']] = $attachmentId;
                $created++;
            }
        }
        if ($created > 0) {
            update_option(\Jumpnrun\Config\ConfigService::OPTION_KEY, $settings);
            \Jumpnrun\Config\ConfigService::clearCache();
        }
        return $created;
    }

    private function hasAnyAssets(): bool
    {
        foreach ([AssetPostTypes::BACKGROUND, AssetPostTypes::OBSTACLE, AssetPostTypes::PLATFORM] as $type) {
            $query = new WP_Query([
                'post_type' => $type,
                'post_status' => 'any',
                'posts_per_page' => 1,
                'fields' => 'ids',
                'no_found_rows' => false,
            ]);
            if ((int) $query->found_posts > 0) {
                return true;
            }
        }
        return false;
    }

    private function seedBackground(string $slug, string $title, int $level): bool
    {
        $postId = wp_insert_post([
            'post_type' => AssetPostTypes::BACKGROUND,
            'post_status' => 'publish',
            'post_title' => $title,
        ]);
        if (!is_int($postId) || $postId <= 0) {
            return false;
        }
        update_post_meta($postId, '_jnr_bg_level', $level);
        update_post_meta($postId, '_jnr_bg_weight', 1);
        $attachmentId = $this->importSprite($slug . '.png', $title);
        if ($attachmentId > 0) {
            set_post_thumbnail($postId, $attachmentId);
        }
        return true;
    }

    private function seedObstacle(string $slug, string $title): bool
    {
        $postId = wp_insert_post([
            'post_type' => AssetPostTypes::OBSTACLE,
            'post_status' => 'publish',
            'post_title' => $title,
        ]);
        if (!is_int($postId) || $postId <= 0) {
            return false;
        }
        update_post_meta($postId, '_jnr_ob_width', 80);
        update_post_meta($postId, '_jnr_ob_height', 80);
        update_post_meta($postId, '_jnr_ob_min_level', 1);
        update_post_meta($postId, '_jnr_ob_weight', 1);
        $attachmentId = $this->importSprite($slug . '.png', $title);
        if ($attachmentId > 0) {
            set_post_thumbnail($postId, $attachmentId);
        }
        return true;
    }

    private function seedPlatform(string $slug, string $title): bool
    {
        $postId = wp_insert_post([
            'post_type' => AssetPostTypes::PLATFORM,
            'post_status' => 'publish',
            'post_title' => $title,
        ]);
        if (!is_int($postId) || $postId <= 0) {
            return false;
        }
        update_post_meta($postId, '_jnr_pf_width', 120);
        update_post_meta($postId, '_jnr_pf_height', 18);
        update_post_meta($postId, '_jnr_pf_weight', 1);
        $attachmentId = $this->importSprite($slug . '.png', $title);
        if ($attachmentId > 0) {
            set_post_thumbnail($postId, $attachmentId);
        }
        return true;
    }

    private function importSprite(string $filename, string $title): int
    {
        $source = JUMPNRUN_DIR . 'assets/sprites/' . $filename;
        if (!file_exists($source)) {
            return 0;
        }
        $tmp = wp_tempnam($filename);
        if ($tmp === '' || !copy($source, $tmp)) {
            return 0;
        }
        $file = [
            'name' => $filename,
            'tmp_name' => $tmp,
        ];
        $sideload = wp_handle_sideload($file, ['test_form' => false]);
        if (!is_array($sideload) || isset($sideload['error'])) {
            @unlink($tmp);
            return 0;
        }
        $attachmentId = wp_insert_attachment([
            'guid' => (string) $sideload['url'],
            'post_mime_type' => (string) $sideload['type'],
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'inherit',
        ], (string) $sideload['file']);
        if (!is_int($attachmentId) || $attachmentId <= 0) {
            return 0;
        }
        $metadata = wp_generate_attachment_metadata($attachmentId, (string) $sideload['file']);
        wp_update_attachment_metadata($attachmentId, $metadata);
        return $attachmentId;
    }
}
