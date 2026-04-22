<?php

declare(strict_types=1);

namespace Jumpnrun\Admin;

use Jumpnrun\Assets\AssetPostTypes;
use Jumpnrun\Assets\AssetRepository;
use WP_Post;

/**
 * Meta-Boxes fuer die beiden Asset-CPTs. Backgrounds bekommen Level + Weight,
 * Obstacles zusaetzlich Collision-Dimensions und min_level fuer progressive
 * Difficulty. Die Meta-Box rendert Number-Inputs mit sinnvollen Ranges und
 * speichert beim save_post via absint() + Nonce-Check.
 */
final class AssetMetaBoxes
{
    private const NONCE_FIELD = 'jnr_asset_meta_nonce';
    private const NONCE_ACTION = 'jnr_save_asset_meta';

    public function register(): void
    {
        add_action('add_meta_boxes', [$this, 'addMetaBoxes']);
        add_action('save_post_' . AssetPostTypes::BACKGROUND, [$this, 'saveBackground'], 10, 2);
        add_action('save_post_' . AssetPostTypes::OBSTACLE, [$this, 'saveObstacle'], 10, 2);
        add_action('save_post_' . AssetPostTypes::PLATFORM, [$this, 'savePlatform'], 10, 2);
    }

    public function addMetaBoxes(): void
    {
        // 'normal'/'high' = Hauptspalte, oberhalb. Sonst klebt alles in der
        // schmalen Side-Spalte und der Hauptbereich (ohne Editor-Support)
        // ist komplett leer.
        add_meta_box(
            'jnr_background_meta',
            'Level-Zuordnung',
            [$this, 'renderBackgroundBox'],
            AssetPostTypes::BACKGROUND,
            'normal',
            'high'
        );

        add_meta_box(
            'jnr_obstacle_meta',
            'Hindernis-Einstellungen',
            [$this, 'renderObstacleBox'],
            AssetPostTypes::OBSTACLE,
            'normal',
            'high'
        );

        add_meta_box(
            'jnr_platform_meta',
            'Plattform-Einstellungen',
            [$this, 'renderPlatformBox'],
            AssetPostTypes::PLATFORM,
            'normal',
            'high'
        );
    }

    public function renderBackgroundBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $level = (int) get_post_meta($post->ID, '_jnr_bg_level', true);
        $weight = (int) get_post_meta($post->ID, '_jnr_bg_weight', true);
        if ($level <= 0) {
            $level = 1;
        }
        if ($weight <= 0) {
            $weight = 1;
        }

        ?>
        <p>
            <label for="jnr_bg_level"><strong>Level (1–10)</strong></label><br>
            <input type="number" id="jnr_bg_level" name="jnr_bg_level"
                   value="<?php echo esc_attr((string) $level); ?>"
                   min="1" max="10" step="1" class="small-text">
        </p>
        <p>
            <label for="jnr_bg_weight"><strong>Gewichtung</strong></label><br>
            <input type="number" id="jnr_bg_weight" name="jnr_bg_weight"
                   value="<?php echo esc_attr((string) $weight); ?>"
                   min="1" max="10" step="1" class="small-text">
            <br><small>Höhere Zahl = öfter gezogen wenn mehrere Backgrounds im selben Level.</small>
        </p>
        <p>
            <small>Bild als <em>Beitragsbild</em> rechts setzen (ideal 960×540 px).</small>
        </p>
        <?php
    }

    public function renderObstacleBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $width = (int) get_post_meta($post->ID, '_jnr_ob_width', true);
        $height = (int) get_post_meta($post->ID, '_jnr_ob_height', true);
        $minLevel = (int) get_post_meta($post->ID, '_jnr_ob_min_level', true);
        $weight = (int) get_post_meta($post->ID, '_jnr_ob_weight', true);
        if ($width <= 0) {
            $width = 80;
        }
        if ($height <= 0) {
            $height = 80;
        }
        if ($minLevel <= 0) {
            $minLevel = 1;
        }
        if ($weight <= 0) {
            $weight = 1;
        }

        ?>
        <p>
            <label for="jnr_ob_width"><strong>Breite (px)</strong></label><br>
            <input type="number" id="jnr_ob_width" name="jnr_ob_width"
                   value="<?php echo esc_attr((string) $width); ?>"
                   min="20" max="400" step="1" class="small-text">
        </p>
        <p>
            <label for="jnr_ob_height"><strong>Höhe (px)</strong></label><br>
            <input type="number" id="jnr_ob_height" name="jnr_ob_height"
                   value="<?php echo esc_attr((string) $height); ?>"
                   min="20" max="400" step="1" class="small-text">
            <br><small>Collision-Box-Größe. Darf kleiner als das Bild sein, dann ist das Hindernis "fairer".</small>
        </p>
        <p>
            <label for="jnr_ob_min_level"><strong>Früheste Level-Stufe</strong></label><br>
            <input type="number" id="jnr_ob_min_level" name="jnr_ob_min_level"
                   value="<?php echo esc_attr((string) $minLevel); ?>"
                   min="1" max="10" step="1" class="small-text">
            <br><small>Dieses Hindernis erscheint erst ab Level X.</small>
        </p>
        <p>
            <label for="jnr_ob_weight"><strong>Gewichtung</strong></label><br>
            <input type="number" id="jnr_ob_weight" name="jnr_ob_weight"
                   value="<?php echo esc_attr((string) $weight); ?>"
                   min="1" max="10" step="1" class="small-text">
        </p>
        <?php
    }

    public function renderPlatformBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $width = (int) get_post_meta($post->ID, '_jnr_pf_width', true);
        $height = (int) get_post_meta($post->ID, '_jnr_pf_height', true);
        $weight = (int) get_post_meta($post->ID, '_jnr_pf_weight', true);
        if ($width <= 0) {
            $width = 120;
        }
        if ($height <= 0) {
            $height = 18;
        }
        if ($weight <= 0) {
            $weight = 1;
        }

        ?>
        <p>
            <label for="jnr_pf_width"><strong>Breite (px)</strong></label><br>
            <input type="number" id="jnr_pf_width" name="jnr_pf_width"
                   value="<?php echo esc_attr((string) $width); ?>"
                   min="40" max="600" step="1" class="small-text">
        </p>
        <p>
            <label for="jnr_pf_height"><strong>Höhe (px)</strong></label><br>
            <input type="number" id="jnr_pf_height" name="jnr_pf_height"
                   value="<?php echo esc_attr((string) $height); ?>"
                   min="6" max="200" step="1" class="small-text">
            <br><small>Übliche Plattform-Höhe ~18 px.</small>
        </p>
        <p>
            <label for="jnr_pf_weight"><strong>Gewichtung</strong></label><br>
            <input type="number" id="jnr_pf_weight" name="jnr_pf_weight"
                   value="<?php echo esc_attr((string) $weight); ?>"
                   min="1" max="10" step="1" class="small-text">
        </p>
        <?php
    }

    public function saveBackground(int $postId, WP_Post $post): void
    {
        if (!$this->canSave($postId)) {
            return;
        }
        $level = isset($_POST['jnr_bg_level']) ? absint($_POST['jnr_bg_level']) : 1;
        $weight = isset($_POST['jnr_bg_weight']) ? absint($_POST['jnr_bg_weight']) : 1;
        $level = max(1, min($level, 10));
        $weight = max(1, min($weight, 10));
        update_post_meta($postId, '_jnr_bg_level', $level);
        update_post_meta($postId, '_jnr_bg_weight', $weight);
        AssetRepository::clearCache();
    }

    public function saveObstacle(int $postId, WP_Post $post): void
    {
        if (!$this->canSave($postId)) {
            return;
        }
        $width = isset($_POST['jnr_ob_width']) ? absint($_POST['jnr_ob_width']) : 80;
        $height = isset($_POST['jnr_ob_height']) ? absint($_POST['jnr_ob_height']) : 80;
        $minLevel = isset($_POST['jnr_ob_min_level']) ? absint($_POST['jnr_ob_min_level']) : 1;
        $weight = isset($_POST['jnr_ob_weight']) ? absint($_POST['jnr_ob_weight']) : 1;
        $width = max(20, min($width, 400));
        $height = max(20, min($height, 400));
        $minLevel = max(1, min($minLevel, 10));
        $weight = max(1, min($weight, 10));
        update_post_meta($postId, '_jnr_ob_width', $width);
        update_post_meta($postId, '_jnr_ob_height', $height);
        update_post_meta($postId, '_jnr_ob_min_level', $minLevel);
        update_post_meta($postId, '_jnr_ob_weight', $weight);
        AssetRepository::clearCache();
    }

    public function savePlatform(int $postId, WP_Post $post): void
    {
        if (!$this->canSave($postId)) {
            return;
        }
        $width = isset($_POST['jnr_pf_width']) ? absint($_POST['jnr_pf_width']) : 120;
        $height = isset($_POST['jnr_pf_height']) ? absint($_POST['jnr_pf_height']) : 18;
        $weight = isset($_POST['jnr_pf_weight']) ? absint($_POST['jnr_pf_weight']) : 1;
        $width = max(40, min($width, 600));
        $height = max(6, min($height, 200));
        $weight = max(1, min($weight, 10));
        update_post_meta($postId, '_jnr_pf_width', $width);
        update_post_meta($postId, '_jnr_pf_height', $height);
        update_post_meta($postId, '_jnr_pf_weight', $weight);
        AssetRepository::clearCache();
    }

    private function canSave(int $postId): bool
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return false;
        }
        if (!isset($_POST[self::NONCE_FIELD])) {
            return false;
        }
        $nonce = sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD]));
        if (!wp_verify_nonce($nonce, self::NONCE_ACTION)) {
            return false;
        }
        if (!current_user_can('edit_post', $postId)) {
            return false;
        }
        return true;
    }
}
