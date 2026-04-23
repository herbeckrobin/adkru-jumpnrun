<?php

declare(strict_types=1);

namespace Jumpnrun\Assets;

/**
 * Zwei Custom Post Types als Asset-Pools fuer das Spiel:
 *
 *   jnr_background  — Hintergrund-Bild, zugeordnet zu einem Level (1–10)
 *   jnr_obstacle    — Hindernis-Sprite mit eigener Collision-Geometrie
 *
 * Beide sind nicht oeffentlich (keine Archive, keine Single-URLs),
 * haben aber UI im Admin und sind Gutenberg-fuehig (show_in_rest).
 * Das eigentliche Bild liegt als Featured Image (_thumbnail_id);
 * pro CPT kommen Meta-Felder via AssetMetaBoxes dazu.
 */
final class AssetPostTypes
{
    public const BACKGROUND = 'jnr_background';
    public const OBSTACLE = 'jnr_obstacle';
    public const PLATFORM = 'jnr_platform';

    /** Verdrahtet den init-Hook fuer die CPT-Registrierung. */
    public function register(): void
    {
        add_action('init', [$this, 'registerTypes']);
    }

    /** Registriert die drei Asset-CPTs (Background, Obstacle, Platform). */
    public function registerTypes(): void
    {
        register_post_type(self::BACKGROUND, [
            'label' => 'Hintergründe',
            'labels' => [
                'name' => 'Hintergründe',
                'singular_name' => 'Hintergrund',
                'add_new_item' => 'Neuen Hintergrund anlegen',
                'edit_item' => 'Hintergrund bearbeiten',
                'new_item' => 'Neuer Hintergrund',
                'view_item' => 'Hintergrund ansehen',
                'search_items' => 'Hintergründe suchen',
                'not_found' => 'Keine Hintergründe vorhanden.',
                'not_found_in_trash' => 'Kein Hintergrund im Papierkorb.',
                'menu_name' => 'Hintergründe',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false, // wird als Submenu in AdminMenu eingehaengt
            'show_in_rest' => true,
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-format-image',
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        register_post_type(self::OBSTACLE, [
            'label' => 'Hindernisse',
            'labels' => [
                'name' => 'Hindernisse',
                'singular_name' => 'Hindernis',
                'add_new_item' => 'Neues Hindernis anlegen',
                'edit_item' => 'Hindernis bearbeiten',
                'new_item' => 'Neues Hindernis',
                'view_item' => 'Hindernis ansehen',
                'search_items' => 'Hindernisse suchen',
                'not_found' => 'Keine Hindernisse vorhanden.',
                'not_found_in_trash' => 'Kein Hindernis im Papierkorb.',
                'menu_name' => 'Hindernisse',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-warning',
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);

        register_post_type(self::PLATFORM, [
            'label' => 'Plattformen',
            'labels' => [
                'name' => 'Plattformen',
                'singular_name' => 'Plattform',
                'add_new_item' => 'Neue Plattform anlegen',
                'edit_item' => 'Plattform bearbeiten',
                'new_item' => 'Neue Plattform',
                'view_item' => 'Plattform ansehen',
                'search_items' => 'Plattformen suchen',
                'not_found' => 'Keine Plattformen vorhanden.',
                'not_found_in_trash' => 'Keine Plattform im Papierkorb.',
                'menu_name' => 'Plattformen',
            ],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => false,
            'show_in_rest' => true,
            'supports' => ['title', 'thumbnail'],
            'menu_icon' => 'dashicons-grid-view',
            'has_archive' => false,
            'rewrite' => false,
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }
}
