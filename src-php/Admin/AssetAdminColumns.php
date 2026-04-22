<?php

declare(strict_types=1);

namespace Jumpnrun\Admin;

use Jumpnrun\Assets\AssetPostTypes;
use WP_Query;

/**
 * Admin-List-Tabellen für die Asset-CPTs anpassen:
 *  - Spalten: Vorschau, Titel, Level (Background) bzw. min_level + Größe (Obstacle), Gewichtung
 *  - Sortierbar nach Level/min_level
 *  - Default-Sortierung beim Öffnen der Liste: nach Level aufsteigend
 *
 * Standard-WP-Listen zeigen sonst nur Titel + Datum, was bei einem
 * Pool von 10+ Hintergründen nichts taugt.
 */
final class AssetAdminColumns
{
    public function register(): void
    {
        // Background-Tabelle
        add_filter('manage_' . AssetPostTypes::BACKGROUND . '_posts_columns', [$this, 'backgroundColumns']);
        add_action('manage_' . AssetPostTypes::BACKGROUND . '_posts_custom_column', [$this, 'renderBackgroundColumn'], 10, 2);
        add_filter('manage_edit-' . AssetPostTypes::BACKGROUND . '_sortable_columns', [$this, 'backgroundSortable']);

        // Obstacle-Tabelle
        add_filter('manage_' . AssetPostTypes::OBSTACLE . '_posts_columns', [$this, 'obstacleColumns']);
        add_action('manage_' . AssetPostTypes::OBSTACLE . '_posts_custom_column', [$this, 'renderObstacleColumn'], 10, 2);
        add_filter('manage_edit-' . AssetPostTypes::OBSTACLE . '_sortable_columns', [$this, 'obstacleSortable']);

        // Platform-Tabelle
        add_filter('manage_' . AssetPostTypes::PLATFORM . '_posts_columns', [$this, 'platformColumns']);
        add_action('manage_' . AssetPostTypes::PLATFORM . '_posts_custom_column', [$this, 'renderPlatformColumn'], 10, 2);
        add_filter('manage_edit-' . AssetPostTypes::PLATFORM . '_sortable_columns', [$this, 'platformSortable']);

        // Default-Sort + Meta-Sort-Translation
        add_action('pre_get_posts', [$this, 'applySorting']);
    }

    /**
     * @param array<string, string> $cols
     * @return array<string, string>
     */
    public function backgroundColumns(array $cols): array
    {
        // Reihenfolge: Checkbox, Vorschau, Titel, Level, Gewichtung, Datum.
        $new = [];
        $new['cb'] = $cols['cb'] ?? '<input type="checkbox" />';
        $new['jnr_thumb'] = 'Vorschau';
        $new['title'] = 'Titel';
        $new['jnr_level'] = 'Level';
        $new['jnr_weight'] = 'Gewichtung';
        $new['date'] = $cols['date'] ?? 'Datum';
        return $new;
    }

    public function renderBackgroundColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'jnr_thumb':
                $url = (string) get_the_post_thumbnail_url($postId, 'thumbnail');
                if ($url !== '') {
                    printf('<img src="%s" alt="" style="width:60px;height:auto;border-radius:4px;">', esc_url($url));
                } else {
                    echo '<span style="color:#a00;">kein Bild</span>';
                }
                break;
            case 'jnr_level':
                echo esc_html((string) (int) get_post_meta($postId, '_jnr_bg_level', true));
                break;
            case 'jnr_weight':
                echo esc_html((string) (int) get_post_meta($postId, '_jnr_bg_weight', true));
                break;
        }
    }

    /**
     * @param array<string, string> $cols
     * @return array<string, string>
     */
    public function backgroundSortable(array $cols): array
    {
        $cols['jnr_level'] = 'jnr_level';
        $cols['jnr_weight'] = 'jnr_weight';
        return $cols;
    }

    /**
     * @param array<string, string> $cols
     * @return array<string, string>
     */
    public function obstacleColumns(array $cols): array
    {
        $new = [];
        $new['cb'] = $cols['cb'] ?? '<input type="checkbox" />';
        $new['jnr_thumb'] = 'Vorschau';
        $new['title'] = 'Titel';
        $new['jnr_min_level'] = 'Ab Level';
        $new['jnr_size'] = 'Größe (B×H)';
        $new['jnr_weight'] = 'Gewichtung';
        $new['date'] = $cols['date'] ?? 'Datum';
        return $new;
    }

    public function renderObstacleColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'jnr_thumb':
                $url = (string) get_the_post_thumbnail_url($postId, 'thumbnail');
                if ($url !== '') {
                    printf('<img src="%s" alt="" style="width:48px;height:auto;border-radius:4px;">', esc_url($url));
                } else {
                    echo '<span style="color:#a00;">kein Bild</span>';
                }
                break;
            case 'jnr_min_level':
                echo esc_html((string) (int) get_post_meta($postId, '_jnr_ob_min_level', true));
                break;
            case 'jnr_size':
                $w = (int) get_post_meta($postId, '_jnr_ob_width', true);
                $h = (int) get_post_meta($postId, '_jnr_ob_height', true);
                printf('%d × %d', $w, $h);
                break;
            case 'jnr_weight':
                echo esc_html((string) (int) get_post_meta($postId, '_jnr_ob_weight', true));
                break;
        }
    }

    /**
     * @param array<string, string> $cols
     * @return array<string, string>
     */
    public function obstacleSortable(array $cols): array
    {
        $cols['jnr_min_level'] = 'jnr_min_level';
        $cols['jnr_weight'] = 'jnr_weight';
        return $cols;
    }

    /**
     * @param array<string, string> $cols
     * @return array<string, string>
     */
    public function platformColumns(array $cols): array
    {
        $new = [];
        $new['cb'] = $cols['cb'] ?? '<input type="checkbox" />';
        $new['jnr_thumb'] = 'Vorschau';
        $new['title'] = 'Titel';
        $new['jnr_size'] = 'Größe (B×H)';
        $new['jnr_weight'] = 'Gewichtung';
        $new['date'] = $cols['date'] ?? 'Datum';
        return $new;
    }

    public function renderPlatformColumn(string $column, int $postId): void
    {
        switch ($column) {
            case 'jnr_thumb':
                $url = (string) get_the_post_thumbnail_url($postId, 'thumbnail');
                if ($url !== '') {
                    printf('<img src="%s" alt="" style="width:60px;height:auto;border-radius:4px;">', esc_url($url));
                } else {
                    echo '<span style="color:#a00;">kein Bild</span>';
                }
                break;
            case 'jnr_size':
                $w = (int) get_post_meta($postId, '_jnr_pf_width', true);
                $h = (int) get_post_meta($postId, '_jnr_pf_height', true);
                printf('%d × %d', $w, $h);
                break;
            case 'jnr_weight':
                echo esc_html((string) (int) get_post_meta($postId, '_jnr_pf_weight', true));
                break;
        }
    }

    /**
     * @param array<string, string> $cols
     * @return array<string, string>
     */
    public function platformSortable(array $cols): array
    {
        $cols['jnr_weight'] = 'jnr_weight';
        return $cols;
    }

    /**
     * Übersetzt das Sortier-Argument (Spalten-Slug → meta_key) und setzt
     * den Default-Sort wenn kein 'orderby' vom User gesetzt ist.
     */
    public function applySorting(WP_Query $query): void
    {
        if (!is_admin() || !$query->is_main_query()) {
            return;
        }

        $postType = $query->get('post_type');
        if (
            $postType !== AssetPostTypes::BACKGROUND
            && $postType !== AssetPostTypes::OBSTACLE
            && $postType !== AssetPostTypes::PLATFORM
        ) {
            return;
        }

        $orderby = (string) $query->get('orderby');

        $weightKey = match ($postType) {
            AssetPostTypes::BACKGROUND => '_jnr_bg_weight',
            AssetPostTypes::OBSTACLE => '_jnr_ob_weight',
            AssetPostTypes::PLATFORM => '_jnr_pf_weight',
            default => '_jnr_bg_weight',
        };

        // User-getriggerte Sortierung über Spaltenklick:
        $metaMap = [
            'jnr_level' => '_jnr_bg_level',
            'jnr_weight' => $weightKey,
            'jnr_min_level' => '_jnr_ob_min_level',
        ];
        if (isset($metaMap[$orderby])) {
            $query->set('meta_key', $metaMap[$orderby]);
            $query->set('orderby', 'meta_value_num');
            return;
        }

        // Default-Sort: BG nach Level, Obstacle nach min_level, Platform nach Titel.
        if ($orderby === '') {
            if ($postType === AssetPostTypes::BACKGROUND) {
                $query->set('meta_key', '_jnr_bg_level');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'ASC');
            } elseif ($postType === AssetPostTypes::OBSTACLE) {
                $query->set('meta_key', '_jnr_ob_min_level');
                $query->set('orderby', 'meta_value_num');
                $query->set('order', 'ASC');
            } else {
                $query->set('orderby', 'title');
                $query->set('order', 'ASC');
            }
        }
    }
}
