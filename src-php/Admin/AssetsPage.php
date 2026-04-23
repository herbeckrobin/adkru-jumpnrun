<?php

declare(strict_types=1);

namespace Jumpnrun\Admin;

use Jumpnrun\Assets\AssetPostTypes;
use Jumpnrun\Assets\AssetSeeder;
use Jumpnrun\Config\ConfigSchema;
use Jumpnrun\Config\ConfigService;

/**
 * Eine zentrale Admin-Seite "Spiel-Assets" mit drei Tabs:
 *  - Hintergründe (Liste der jnr_background-CPT-Posts)
 *  - Hindernisse (Liste der jnr_obstacle-CPT-Posts)
 *  - Sprites (Player/Coin/Plattform — Settings-Felder mit Media-Picker)
 *
 * Editieren der Pool-Einträge läuft weiterhin über die Standard-WP-CPT-Editoren
 * (Edit/Add-New). Diese Page bündelt nur Übersicht + Einstieg + Sprite-Form.
 */
final class AssetsPage
{
    public const SLUG = 'jumpnrun_assets';
    public const SAVE_SPRITES_ACTION = 'jumpnrun_save_sprites';

    /** @var array<string, string> tab-slug → Anzeigename */
    private const TABS = [
        'backgrounds' => 'Hintergründe',
        'obstacles' => 'Hindernisse',
        'platforms' => 'Plattformen',
        'sprites' => 'Sprites',
    ];

    /** @var list<string> Settings-Keys die im Sprites-Tab gerendert werden */
    private const SPRITE_KEYS = [
        'playerIdleSprite',
        'playerJumpSprite',
        'coinSprite',
    ];

    /** Haengt den Submit-Handler fuer den Sprites-Tab ein. */
    public function register(): void
    {
        add_action('admin_post_' . self::SAVE_SPRITES_ACTION, [$this, 'handleSpritesSubmit']);
    }

    /** Rendert die Haupt-Admin-Seite mit Tab-Navigation und dem jeweils aktiven Tab. */
    public function render(): void
    {
        if (!current_user_can('edit_posts')) {
            return;
        }

        $tab = isset($_GET['tab']) ? sanitize_key((string) wp_unslash($_GET['tab'])) : 'backgrounds';
        if (!isset(self::TABS[$tab])) {
            $tab = 'backgrounds';
        }

        echo '<div class="wrap jumpnrun-assets">';
        echo '<h1>Spiel-Assets</h1>';
        echo '<p style="color:#646970;max-width:760px;">Hintergründe, Hindernisse und Plattformen werden als Pools gepflegt (mehrere möglich, gewichtet zufällig). Sprites sind feste Slots aus der Mediathek. Wer alles löscht, sieht im Spiel Solid-Color-Fallbacks.</p>';

        $this->renderTabNav($tab);
        $this->renderFlashNotice();
        $this->renderSeedNoticeIfMissing();

        echo '<div class="jnr-tab-body" style="margin-top:18px;">';
        match ($tab) {
            'backgrounds' => $this->renderBackgroundsTab(),
            'obstacles' => $this->renderObstaclesTab(),
            'platforms' => $this->renderPlatformsTab(),
            'sprites' => $this->renderSpritesTab(),
            default => null,
        };
        echo '</div>';
        echo '</div>';

        // Inline-CSS damit der von SettingsPage::renderField mitgelieferte
        // <label class="jnr-label"> in dieser View ebenso versteckt ist.
        echo '<style>.jumpnrun-assets .jnr-label { display: none; }</style>';
    }

    private function renderTabNav(string $active): void
    {
        echo '<nav class="nav-tab-wrapper" style="margin-top:12px;">';
        foreach (self::TABS as $slug => $label) {
            $url = add_query_arg(
                ['page' => self::SLUG, 'tab' => $slug],
                admin_url('admin.php')
            );
            $cls = 'nav-tab' . ($slug === $active ? ' nav-tab-active' : '');
            $badge = $this->badgeFor($slug);
            printf(
                '<a href="%s" class="%s">%s%s</a>',
                esc_url($url),
                esc_attr($cls),
                esc_html($label),
                $badge !== '' ? sprintf(' <span style="opacity:0.6;">(%s)</span>', esc_html($badge)) : ''
            );
        }
        echo '</nav>';
    }

    /**
     * Badge-Text rechts vom Tab-Namen.
     * - Pool-Tabs (BG/Obstacle/Platform): einfacher Count "12"
     * - Sprites-Tab: "filled/max" (z.B. "2/3"), damit Tomy auf einen Blick
     *   sieht ob alle festen Slots gepflegt sind.
     */
    private function badgeFor(string $tab): string
    {
        if ($tab === 'backgrounds') {
            return (string) $this->countPosts(AssetPostTypes::BACKGROUND);
        }
        if ($tab === 'obstacles') {
            return (string) $this->countPosts(AssetPostTypes::OBSTACLE);
        }
        if ($tab === 'platforms') {
            return (string) $this->countPosts(AssetPostTypes::PLATFORM);
        }
        if ($tab === 'sprites') {
            $cfg = get_option(ConfigService::OPTION_KEY, []);
            if (!is_array($cfg)) {
                $cfg = [];
            }
            $filled = 0;
            foreach (self::SPRITE_KEYS as $key) {
                if (!empty($cfg[$key])) {
                    $filled++;
                }
            }
            return sprintf('%d/%d', $filled, count(self::SPRITE_KEYS));
        }
        return '';
    }

    private function countPosts(string $postType): int
    {
        $counts = wp_count_posts($postType);
        if (!is_object($counts)) {
            return 0;
        }
        return isset($counts->publish) ? (int) $counts->publish : 0;
    }

    private function renderFlashNotice(): void
    {
        if (isset($_GET['jnr_sprites_saved'])) {
            echo '<div class="notice notice-success is-dismissible" style="margin-top:14px;"><p>Sprites gespeichert.</p></div>';
        }
        if (isset($_GET['jnr_seeded'])) {
            $count = (int) $_GET['jnr_seeded'];
            if ($count > 0) {
                printf(
                    '<div class="notice notice-success is-dismissible" style="margin-top:14px;"><p>%d Standard-Assets in die Mediathek importiert und als Hintergründe / Hindernisse / Plattformen / Sprites angelegt.</p></div>',
                    $count
                );
            } else {
                echo '<div class="notice notice-info is-dismissible" style="margin-top:14px;"><p>Es fehlten keine Standard-Assets — kein Import notwendig.</p></div>';
            }
        }
    }

    /**
     * Zeigt den Seed-Button nur wenn IRGENDWO Assets fehlen — Pool leer
     * oder Sprite-Slot auf 0. Wenn alles gepflegt ist, ist der Hinweis weg
     * und stört nicht im Daily-Use.
     */
    private function renderSeedNoticeIfMissing(): void
    {
        if ($this->allAssetsPresent()) {
            return;
        }

        $missing = $this->missingAssetTypes();
        $actionUrl = admin_url('admin-post.php');

        printf(
            '<div class="notice notice-warning" style="padding:12px 14px;margin:14px 0;">'
            . '<p style="margin-top:0;"><strong>Standard-Assets unvollständig:</strong> %s</p>'
            . '<p style="margin-bottom:8px;color:#646970;">Mit einem Klick werden die mitgelieferten Default-Bilder einmalig in deine Mediathek importiert. Bestehende Einträge bleiben unangetastet.</p>'
            . '<form method="post" action="%s" style="margin:0;">'
            . '%s'
            . '<input type="hidden" name="action" value="%s">'
            . '<button type="submit" class="button button-primary">Standard-Sprites importieren</button>'
            . '</form>'
            . '</div>',
            esc_html(implode(', ', $missing)),
            esc_url($actionUrl),
            wp_nonce_field(AssetSeeder::ACTION, '_wpnonce', true, false),
            esc_attr(AssetSeeder::ACTION)
        );
    }

    private function allAssetsPresent(): bool
    {
        return $this->missingAssetTypes() === [];
    }

    /** @return list<string> Anzeigenamen der Asset-Typen die noch leer sind */
    private function missingAssetTypes(): array
    {
        $missing = [];
        if ($this->countPosts(AssetPostTypes::BACKGROUND) === 0) {
            $missing[] = 'Hintergründe';
        }
        if ($this->countPosts(AssetPostTypes::OBSTACLE) === 0) {
            $missing[] = 'Hindernisse';
        }
        if ($this->countPosts(AssetPostTypes::PLATFORM) === 0) {
            $missing[] = 'Plattformen';
        }
        $cfg = get_option(ConfigService::OPTION_KEY, []);
        if (!is_array($cfg)) {
            $cfg = [];
        }
        $spriteLabels = [
            'playerIdleSprite' => 'Spielfigur (Moving)',
            'playerJumpSprite' => 'Spielfigur (Sprung)',
            'coinSprite' => 'Coin',
        ];
        foreach ($spriteLabels as $key => $label) {
            if (empty($cfg[$key])) {
                $missing[] = $label;
            }
        }
        return $missing;
    }

    private function renderBackgroundsTab(): void
    {
        $this->renderListTab(
            'backgrounds',
            AssetPostTypes::BACKGROUND,
            'Werden pro Level zugewiesen, gewichtet zufällig gezogen.',
            [
                'cb' => '<input type="checkbox" />',
                'jnr_thumb' => 'Vorschau',
                'title' => 'Titel',
                'jnr_level' => 'Level',
                'jnr_weight' => 'Gewichtung',
                'date' => 'Datum',
            ],
            [
                'title' => ['title', false],
                'jnr_level' => ['jnr_level', false],
                'jnr_weight' => ['jnr_weight', false],
                'date' => ['date', true],
            ],
            [
                'jnr_level' => '_jnr_bg_level',
                'jnr_weight' => '_jnr_bg_weight',
            ],
            'jnr_level'
        );
    }

    private function renderObstaclesTab(): void
    {
        $this->renderListTab(
            'obstacles',
            AssetPostTypes::OBSTACLE,
            'Hindernisse mit minimalem Level + individueller Hitbox-Größe.',
            [
                'cb' => '<input type="checkbox" />',
                'jnr_thumb' => 'Vorschau',
                'title' => 'Titel',
                'jnr_min_level' => 'Ab Level',
                'jnr_size' => 'Größe (B×H)',
                'jnr_weight' => 'Gewichtung',
                'date' => 'Datum',
            ],
            [
                'title' => ['title', false],
                'jnr_min_level' => ['jnr_min_level', false],
                'jnr_weight' => ['jnr_weight', false],
                'date' => ['date', true],
            ],
            [
                'jnr_min_level' => '_jnr_ob_min_level',
                'jnr_weight' => '_jnr_ob_weight',
                'jnr_width' => '_jnr_ob_width',
                'jnr_height' => '_jnr_ob_height',
            ],
            'jnr_min_level'
        );
    }

    private function renderPlatformsTab(): void
    {
        $this->renderListTab(
            'platforms',
            AssetPostTypes::PLATFORM,
            'Plattformen werden zu Spielbeginn zufällig (gewichtet) aus dem Pool gezogen.',
            [
                'cb' => '<input type="checkbox" />',
                'jnr_thumb' => 'Vorschau',
                'title' => 'Titel',
                'jnr_size' => 'Größe (B×H)',
                'jnr_weight' => 'Gewichtung',
                'date' => 'Datum',
            ],
            [
                'title' => ['title', false],
                'jnr_weight' => ['jnr_weight', false],
                'date' => ['date', true],
            ],
            [
                'jnr_weight' => '_jnr_pf_weight',
                'jnr_width' => '_jnr_pf_width',
                'jnr_height' => '_jnr_pf_height',
            ],
            'title'
        );
    }

    /**
     * Generischer Pool-Tab-Renderer auf Basis von WP_List_Table.
     * Liefert die gleichen Features wie die Standard-edit.php-Liste:
     * Bulk-Actions, Trash/Restore, Pagination, Suche, sortierbare Spalten.
     *
     * @param array<string,string> $columns
     * @param array<string,array{0:string,1:bool}> $sortable
     * @param array<string,string> $metaKeys
     */
    private function renderListTab(
        string $tabSlug,
        string $postType,
        string $description,
        array $columns,
        array $sortable,
        array $metaKeys,
        string $defaultOrderby
    ): void {
        $newUrl = admin_url('post-new.php?post_type=' . $postType);

        echo '<p style="margin:8px 0;color:#646970;">' . esc_html($description) . '</p>';
        echo '<p style="margin:0 0 8px;"><a href="' . esc_url($newUrl) . '" class="page-title-action">Neu hinzufügen</a></p>';

        $table = new AssetListTable([
            'post_type' => $postType,
            'columns' => $columns,
            'sortable' => $sortable,
            'meta_keys' => $metaKeys,
            'default_orderby' => $defaultOrderby,
        ]);
        $table->prepare_items();
        $table->views();

        // method="get": Suche, Filter, Pagination und Bulk-Actions laufen
        // ueber URL-Parameter wie in WP-Standard. Versteckte Inputs halten
        // den User in unserem Tab statt zur edit.php zu redirecten.
        echo '<form method="get">';
        printf('<input type="hidden" name="page" value="%s">', esc_attr(self::SLUG));
        printf('<input type="hidden" name="tab" value="%s">', esc_attr($tabSlug));
        if (isset($_GET['post_status'])) {
            printf(
                '<input type="hidden" name="post_status" value="%s">',
                esc_attr(sanitize_key((string) $_GET['post_status']))
            );
        }
        $table->search_box('Suchen', 'jnr-search-' . $tabSlug);
        $table->display();
        echo '</form>';
    }

    private function renderSpritesTab(): void
    {
        $current = get_option(ConfigService::OPTION_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }

        $fieldMap = ConfigSchema::fieldMap();
        $actionUrl = admin_url('admin-post.php');

        echo '<p style="margin:0 0 16px;color:#646970;">Spielfigur, Coin und Plattformen — feste Slots aus der Mediathek. „Entfernen" setzt den Slot zurück; das Spiel zeigt dann den Solid-Color-Fallback aus der Engine.</p>';

        echo '<form method="post" action="' . esc_url($actionUrl) . '">';
        wp_nonce_field(self::SAVE_SPRITES_ACTION);
        echo '<input type="hidden" name="action" value="' . esc_attr(self::SAVE_SPRITES_ACTION) . '">';
        echo '<table class="form-table" role="presentation"><tbody>';

        foreach (self::SPRITE_KEYS as $key) {
            if (!isset($fieldMap[$key])) {
                continue;
            }
            $spec = $fieldMap[$key];
            echo '<tr>';
            printf('<th scope="row">%s</th>', esc_html((string) $spec['label']));
            echo '<td>';
            SettingsPage::renderField($key, $spec, $current);
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        submit_button('Sprites speichern');
        echo '</form>';
    }

    /** Persistiert die im Sprites-Tab gewaehlten Attachment-IDs inklusive "Entfernen"-Handling. */
    public function handleSpritesSubmit(): void
    {
        check_admin_referer(self::SAVE_SPRITES_ACTION);
        if (!current_user_can('manage_options')) {
            wp_die('Keine Berechtigung.', 'Jump-n-Run', ['response' => 403]);
        }

        $current = get_option(ConfigService::OPTION_KEY, []);
        if (!is_array($current)) {
            $current = [];
        }

        $raw = [];
        if (isset($_POST[ConfigService::OPTION_KEY]) && is_array($_POST[ConfigService::OPTION_KEY])) {
            $raw = wp_unslash($_POST[ConfigService::OPTION_KEY]);
        }

        // "Entfernen"-Buttons sind Submit-Buttons mit name="jnr_clear[<key>]".
        // Wenn POST enthaelt jnr_clear[playerIdleSprite]=1, wird der Slot
        // unabhaengig vom Hidden-Input auf 0 gesetzt. Macht das Entfernen
        // robust gegen JS-Aussetzer.
        $clearList = [];
        if (isset($_POST['jnr_clear']) && is_array($_POST['jnr_clear'])) {
            foreach (array_keys($_POST['jnr_clear']) as $clearKey) {
                if (is_string($clearKey)) {
                    $clearList[sanitize_key($clearKey)] = true;
                }
            }
        }

        $fieldMap = ConfigSchema::fieldMap();
        foreach (self::SPRITE_KEYS as $key) {
            if (!isset($fieldMap[$key])) {
                continue;
            }
            if (isset($clearList[$key])) {
                $current[$key] = 0;
            } else {
                $current[$key] = ConfigSchema::sanitizeValue($raw[$key] ?? 0, $fieldMap[$key]);
            }
        }

        update_option(ConfigService::OPTION_KEY, $current);
        ConfigService::clearCache();

        wp_safe_redirect(add_query_arg(
            [
                'page' => self::SLUG,
                'tab' => 'sprites',
                'jnr_sprites_saved' => '1',
            ],
            admin_url('admin.php')
        ));
        exit;
    }
}
