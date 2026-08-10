<?php

declare(strict_types=1);

namespace Jumpnrun\Tests\Shortcode;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Jumpnrun\Embed\GameRenderer;
use Jumpnrun\Shortcode\GameShortcode;
use PHPUnit\Framework\TestCase;

/**
 * Regressionstest für den Elementor-Blocker.
 *
 * Bis v0.6.4 hing das Laden von client.js an
 * `wp_enqueue_scripts` + `has_shortcode($post->post_content, 'jumpnrun')`.
 * Page-Builder speichern den Seiteninhalt aber in Postmeta, bei Elementor in
 * `_elementor_data`. Die Prüfung lief damit ins Leere: das Markup rendert,
 * das Bundle fehlt, der Besucher sieht eine leere Fläche.
 *
 * Gemessen auf einer echten Elementor-Instanz vor dem Fix:
 *   /test-klassisch/  liefert client.js
 *   /test-elementor/  liefert kein client.js
 *
 * Diese Tests halten fest, dass das Enqueue nicht mehr an einer
 * Content-Heuristik hängt.
 */
final class GameShortcodeEnqueueTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    /**
     * Der Kern des Regressionsschutzes: sobald das Enqueue wieder an
     * `wp_enqueue_scripts` hängt, läuft es zwangsläufig vor dem Rendern und
     * braucht dann erneut eine Heuristik, ob die Seite das Spiel einbindet.
     * Genau daran ist es unter Elementor gescheitert.
     */
    public function testRegisterDoesNotHookAssetLoadingIntoWpEnqueueScripts(): void
    {
        $hooks = [];

        Functions\when('add_shortcode')->justReturn(true);
        Functions\when('add_action')->alias(
            static function (string $hook) use (&$hooks): bool {
                $hooks[] = $hook;
                return true;
            }
        );

        (new GameShortcode())->register();

        self::assertNotContains(
            'wp_enqueue_scripts',
            $hooks,
            'Assets dürfen nur beim Rendern geladen werden, sonst greift wieder eine Content-Heuristik.'
        );
    }

    /** Der Shortcode selbst muss weiterhin registriert werden. */
    public function testRegisterRegistersTheShortcode(): void
    {
        $registered = [];

        Functions\when('add_action')->justReturn(true);
        Functions\when('add_shortcode')->alias(
            static function (string $tag) use (&$registered): bool {
                $registered[] = $tag;
                return true;
            }
        );

        (new GameShortcode())->register();

        self::assertContains(GameShortcode::TAG, $registered);
    }

    /**
     * enqueueAssets() darf keine Annahme über $post treffen. Der Test läuft
     * bewusst ohne globalen Post: das entspricht dem Elementor-Fall, in dem
     * die alte Prüfung abgebrochen hat.
     */
    public function testEnqueueAssetsLoadsClientBundleWithoutAnyPostContext(): void
    {
        unset($GLOBALS['post']);

        $modules = [];

        Functions\when('wp_enqueue_script_module')->alias(
            static function (string $handle) use (&$modules): void {
                $modules[] = $handle;
            }
        );
        Functions\when('wp_enqueue_style')->justReturn(true);

        (new GameRenderer())->enqueueAssets();

        self::assertSame(['jumpnrun-client'], $modules);
    }

    /** Das Bundle wird als Script-Modul geladen, nicht als klassisches Script. */
    public function testClientIsLoadedAsScriptModuleNotAsClassicScript(): void
    {
        $classic = 0;

        Functions\when('wp_enqueue_script_module')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_enqueue_script')->alias(
            static function () use (&$classic): void {
                $classic++;
            }
        );

        (new GameRenderer())->enqueueAssets();

        self::assertSame(0, $classic, 'Vite liefert ein ES-Modul, wp_enqueue_script bindet es falsch ein.');
    }
}
