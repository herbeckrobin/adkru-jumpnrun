<?php

declare(strict_types=1);

namespace Jumpnrun;

use Jumpnrun\Admin\AdminMenu;
use Jumpnrun\Admin\AssetAdminColumns;
use Jumpnrun\Admin\AssetMetaBoxes;
use Jumpnrun\Admin\AssetsPage;
use Jumpnrun\Api\RestController;
use Jumpnrun\Assets\AssetPostTypes;
use Jumpnrun\Assets\AssetSeeder;
use Jumpnrun\Db\Schema;
use Jumpnrun\Shortcode\GameShortcode;
use Jumpnrun\Shortcode\ScoreboardShortcode;

final class Plugin
{
    private static ?self $instance = null;

    public static function boot(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->register();
        }
        return self::$instance;
    }

    private function register(): void
    {
        register_activation_hook(JUMPNRUN_FILE, [Schema::class, 'install']);

        add_action('plugins_loaded', [Schema::class, 'maybeUpgrade'], 20);
        add_action('init', [$this, 'registerShortcodes']);
        add_action('rest_api_init', [new RestController(), 'registerRoutes']);

        // Asset-Pools (CPTs) sind auch im Frontend noetig — Registrierung im init.
        (new AssetPostTypes())->register();

        if (is_admin()) {
            (new AdminMenu())->register();
            (new AssetMetaBoxes())->register();
            (new AssetAdminColumns())->register();
            (new AssetsPage())->register();
            (new AssetSeeder())->register();
        }
    }

    public function registerShortcodes(): void
    {
        (new GameShortcode())->register();
        (new ScoreboardShortcode())->register();
    }
}
