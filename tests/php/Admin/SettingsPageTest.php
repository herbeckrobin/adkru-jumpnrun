<?php

declare(strict_types=1);

namespace Jumpnrun\Tests\Admin;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Jumpnrun\Admin\SettingsPage;
use Jumpnrun\Config\ConfigService;
use PHPUnit\Framework\TestCase;

/**
 * Verhindert das Wegspeichern von Sprite-Override-IDs und anderen Tab-
 * Feldern beim Save eines einzelnen Settings-Tabs. Vor v0.6.4 hat sanitize()
 * mit leerem Array gestartet und alle nicht-submitted Felder geloescht.
 */
final class SettingsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        ConfigService::clearCache();
        // sanitizeValue ruft WP-Funktionen — fuer den Test no-op stubs.
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->returnArg();
        Functions\when('absint')->alias(static fn ($v): int => (int) $v < 0 ? 0 : (int) $v);
        // get_post_type wird in sanitizeAttachment aufgerufen — fuer den
        // Test gehen wir davon aus, dass uebergebene IDs gueltige Attachments
        // sind (echte Validierung passiert bei AssetsPage::handleSpritesSubmit).
        Functions\when('get_post_type')->justReturn('attachment');
        Functions\when('add_settings_error')->justReturn(null);
    }

    protected function tearDown(): void
    {
        ConfigService::clearCache();
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testSavingPhysicsTabPreservesSpriteOverrides(): void
    {
        // Bestehende Option mit Sprite-Override + Physik-Wert.
        Functions\when('get_option')->justReturn([
            'playerIdleSprite' => 42,
            'playerJumpSprite' => 43,
            'coinSprite' => 44,
            'gravity' => 1.5,
        ]);

        $page = new SettingsPage();
        // Submit simuliert Physik-Tab: nur gravity ist im POST.
        $out = $page->sanitize(['gravity' => 2.0]);

        self::assertSame(42, $out['playerIdleSprite'], 'Sprite-Override darf nicht verloren gehen');
        self::assertSame(43, $out['playerJumpSprite']);
        self::assertSame(44, $out['coinSprite']);
        self::assertSame(2.0, $out['gravity'], 'Submitted gravity wird uebernommen');
    }

    public function testSavingLayoutTabSwitchesBoolFromTrueToFalse(): void
    {
        // Bestehende Option: showScoreboard ist aktiv.
        Functions\when('get_option')->justReturn([
            'showScoreboard' => true,
            'scoreboardLimit' => 10,
        ]);

        $page = new SettingsPage();
        // Submit simuliert Layout-Tab mit unchecked Checkbox: das Hidden-
        // Input mit value="0" sendet den Wert 0 mit. scoreboardLimit bleibt 10.
        $out = $page->sanitize(['showScoreboard' => '0', 'scoreboardLimit' => '10']);

        self::assertFalse($out['showScoreboard'], 'Bool muss auf false setzbar sein');
        self::assertSame(10, $out['scoreboardLimit']);
    }

    public function testSavingTabDoesNotResetUnrelatedFields(): void
    {
        // Bestehende Option: viele Felder aus verschiedenen Sections.
        Functions\when('get_option')->justReturn([
            'canvasWidth' => 1280,         // Canvas-Tab
            'gravity' => 2.5,              // Physik-Tab
            'hitboxBuffer' => 5,           // Hitbox-Tab
            'discountCode' => 'FOO20',     // Branding-Tab
            'showScoreboard' => true,      // Layout-Tab
            'minViewportWidth' => 600,     // Viewport-Tab
            'playerIdleSprite' => 42,      // Sprites (assets-page)
        ]);

        $page = new SettingsPage();
        // User aendert nur ein einzelnes Feld im Physik-Tab.
        $out = $page->sanitize(['gravity' => 1.8]);

        // Das geaenderte Feld kommt durch.
        self::assertSame(1.8, $out['gravity']);
        // Alle anderen muessen unveraendert bleiben.
        self::assertSame(1280, $out['canvasWidth']);
        self::assertSame(5, $out['hitboxBuffer']);
        self::assertSame('FOO20', $out['discountCode']);
        self::assertTrue($out['showScoreboard']);
        self::assertSame(600, $out['minViewportWidth']);
        self::assertSame(42, $out['playerIdleSprite']);
    }

    public function testEmptyExistingOptionStillWorks(): void
    {
        // Erster Save je: Option ist leer/nicht vorhanden.
        Functions\when('get_option')->justReturn([]);

        $page = new SettingsPage();
        $out = $page->sanitize(['gravity' => 2.0]);

        self::assertSame(2.0, $out['gravity']);
        self::assertCount(1, $out, 'Ohne existing nur das submitted Feld');
    }

    public function testNonArrayExistingOptionFallbackToEmpty(): void
    {
        // Defekte Option (z.B. nach Migration) — get_option liefert false oder string.
        Functions\when('get_option')->justReturn(false);

        $page = new SettingsPage();
        $out = $page->sanitize(['gravity' => 2.0]);

        self::assertSame(2.0, $out['gravity']);
    }

    public function testInvalidInputReturnsEmpty(): void
    {
        Functions\when('get_option')->justReturn(['gravity' => 1.5]);
        Functions\when('clearCache')->justReturn(null);

        $page = new SettingsPage();
        $out = $page->sanitize('not an array');

        // Kein Crash, leeres Array zurueck — bestehende Option nicht touched.
        self::assertSame([], $out);
    }
}
