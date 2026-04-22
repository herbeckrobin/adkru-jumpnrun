<?php

declare(strict_types=1);

namespace Jumpnrun\Assets;

use WP_Query;

/**
 * Liefert die Asset-Pools fuer den Shortcode. Gecachet via Transient
 * (5 Minuten), Invalidierung bei save_post auf den beiden CPTs.
 *
 * Rueckgabe-Format:
 *
 *   [
 *     'backgrounds' => [
 *        '1' => [ ['url'=>..., 'weight'=>1], ... ],
 *        '2' => [...]
 *     ],
 *     'obstacles' => [
 *        ['url'=>..., 'width'=>80, 'height'=>80, 'minLevel'=>1, 'weight'=>1],
 *        ...
 *     ],
 *   ]
 *
 * Fehlen die CPTs (frisches Plugin ohne Seed), liefert der Repository
 * einen leeren Pool — die JS-Seite faellt dann auf ihre eigenen Defaults
 * zurueck. Im Regelbetrieb seeded AssetSeeder direkt beim Aktivieren.
 */
final class AssetRepository
{
    private const CACHE_KEY = 'jumpnrun_asset_pools';
    private const CACHE_TTL = 5 * MINUTE_IN_SECONDS;

    /** @return array{backgrounds: array<string, list<array{url:string,weight:int}>>, obstacles: list<array{url:string,width:int,height:int,minLevel:int,weight:int}>, platforms: list<array{url:string,width:int,height:int,weight:int}>} */
    public static function pools(): array
    {
        $cached = get_transient(self::CACHE_KEY);
        if (is_array($cached) && isset($cached['backgrounds'], $cached['obstacles'], $cached['platforms'])) {
            /** @var array{backgrounds: array<string, list<array{url:string,weight:int}>>, obstacles: list<array{url:string,width:int,height:int,minLevel:int,weight:int}>, platforms: list<array{url:string,width:int,height:int,weight:int}>} $cached */
            return $cached;
        }

        $pools = [
            'backgrounds' => self::loadBackgrounds(),
            'obstacles' => self::loadObstacles(),
            'platforms' => self::loadPlatforms(),
        ];

        set_transient(self::CACHE_KEY, $pools, self::CACHE_TTL);
        return $pools;
    }

    public static function clearCache(): void
    {
        delete_transient(self::CACHE_KEY);
    }

    /** @return array<string, list<array{url:string,weight:int}>> */
    private static function loadBackgrounds(): array
    {
        $query = new WP_Query([
            'post_type' => AssetPostTypes::BACKGROUND,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'no_found_rows' => true,
            'fields' => 'ids',
        ]);

        $byLevel = [];
        foreach ($query->posts as $postId) {
            if (!is_int($postId)) {
                continue;
            }
            $level = (int) get_post_meta($postId, '_jnr_bg_level', true);
            $weight = (int) get_post_meta($postId, '_jnr_bg_weight', true);
            if ($level <= 0) {
                $level = 1;
            }
            if ($weight <= 0) {
                $weight = 1;
            }
            $url = self::attachmentUrl((int) get_post_thumbnail_id($postId));
            if ($url === '') {
                continue;
            }
            $key = (string) $level;
            if (!isset($byLevel[$key])) {
                $byLevel[$key] = [];
            }
            $byLevel[$key][] = [
                'url' => $url,
                'weight' => $weight,
            ];
        }
        return $byLevel;
    }

    /** @return list<array{url:string,width:int,height:int,minLevel:int,weight:int}> */
    private static function loadObstacles(): array
    {
        $query = new WP_Query([
            'post_type' => AssetPostTypes::OBSTACLE,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'no_found_rows' => true,
            'fields' => 'ids',
        ]);

        $out = [];
        foreach ($query->posts as $postId) {
            if (!is_int($postId)) {
                continue;
            }
            $url = self::attachmentUrl((int) get_post_thumbnail_id($postId));
            if ($url === '') {
                continue;
            }
            $width = (int) get_post_meta($postId, '_jnr_ob_width', true);
            $height = (int) get_post_meta($postId, '_jnr_ob_height', true);
            $minLevel = (int) get_post_meta($postId, '_jnr_ob_min_level', true);
            $weight = (int) get_post_meta($postId, '_jnr_ob_weight', true);
            $out[] = [
                'url' => $url,
                'width' => $width > 0 ? $width : 80,
                'height' => $height > 0 ? $height : 80,
                'minLevel' => $minLevel > 0 ? $minLevel : 1,
                'weight' => $weight > 0 ? $weight : 1,
            ];
        }
        return $out;
    }

    /** @return list<array{url:string,width:int,height:int,weight:int}> */
    private static function loadPlatforms(): array
    {
        $query = new WP_Query([
            'post_type' => AssetPostTypes::PLATFORM,
            'post_status' => 'publish',
            'posts_per_page' => 200,
            'no_found_rows' => true,
            'fields' => 'ids',
        ]);

        $out = [];
        foreach ($query->posts as $postId) {
            if (!is_int($postId)) {
                continue;
            }
            $url = self::attachmentUrl((int) get_post_thumbnail_id($postId));
            if ($url === '') {
                continue;
            }
            $width = (int) get_post_meta($postId, '_jnr_pf_width', true);
            $height = (int) get_post_meta($postId, '_jnr_pf_height', true);
            $weight = (int) get_post_meta($postId, '_jnr_pf_weight', true);
            $out[] = [
                'url' => $url,
                'width' => $width > 0 ? $width : 120,
                'height' => $height > 0 ? $height : 18,
                'weight' => $weight > 0 ? $weight : 1,
            ];
        }
        return $out;
    }

    private static function attachmentUrl(int $attachmentId): string
    {
        if ($attachmentId <= 0) {
            return '';
        }
        $url = wp_get_attachment_url($attachmentId);
        return is_string($url) ? $url : '';
    }
}
