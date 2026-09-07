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

        $scripts = [];

        Functions\when('wp_enqueue_script')->alias(
            static function (string $handle) use (&$scripts): void {
                $scripts[] = $handle;
            }
        );
        Functions\when('wp_enqueue_style')->justReturn(true);

        (new GameRenderer())->enqueueAssets();

        self::assertSame([GameRenderer::SCRIPT_HANDLE], $scripts);
    }

    /**
     * Das Bundle läuft als klassisches Script, nicht über die
     * Script-Modules-API.
     *
     * WordPress druckt Script Modules unter einem Block-Theme in wp_head
     * (wp-includes/class-wp-script-modules.php: wp_is_block_theme() ? 'wp_head'
     * : 'wp_footer'). Das Enqueue passiert aber erst beim Rendern des Inhalts,
     * da ist wp_head durch: das Modul wird registriert und nie gedruckt, das
     * Spiel bleibt unsichtbar. Genau das ist auf adkru.de passiert, als das
     * Classic-Theme durch ein Block-Theme ersetzt wurde (07.09.2026).
     */
    public function testClientIsLoadedAsClassicScriptSoBlockThemesPrintIt(): void
    {
        $modules = 0;

        Functions\when('wp_enqueue_script')->justReturn(true);
        Functions\when('wp_enqueue_style')->justReturn(true);
        Functions\when('wp_enqueue_script_module')->alias(
            static function () use (&$modules): void {
                $modules++;
            }
        );

        (new GameRenderer())->enqueueAssets();

        self::assertSame(
            0,
            $modules,
            'Script Modules landen unter Block-Themes in wp_head und werden beim Rendern nie gedruckt.'
        );
    }

    /**
     * Das Bundle ist ein ES-Modul (top-level export), ohne type="module" wirft
     * der Browser einen SyntaxError. Das Attribut kommt vom Filter nach.
     */
    public function testScriptTagFilterAddsTypeModuleForTheBundleOnly(): void
    {
        $filter = null;

        Functions\when('add_filter')->alias(
            static function (string $hook, callable $cb) use (&$filter): bool {
                if ('script_loader_tag' === $hook) {
                    $filter = $cb;
                }
                return true;
            }
        );

        GameRenderer::registerScriptTypeFilter();

        self::assertIsCallable($filter, 'Der Filter auf script_loader_tag fehlt.');

        $eigenes = '<script src="client.js" id="jumpnrun-client-js"></script>';
        $fremdes = '<script src="jquery.js" id="jquery-core-js"></script>';

        self::assertSame(
            '<script type="module" src="client.js" id="jumpnrun-client-js"></script>',
            $filter($eigenes, GameRenderer::SCRIPT_HANDLE)
        );
        self::assertSame($fremdes, $filter($fremdes, 'jquery-core'));
        self::assertSame(
            '<script type="module" src="client.js"></script>',
            $filter('<script type="module" src="client.js"></script>', GameRenderer::SCRIPT_HANDLE),
            'Ein bereits gesetztes type="module" darf nicht doppelt eingefügt werden.'
        );
    }
}
