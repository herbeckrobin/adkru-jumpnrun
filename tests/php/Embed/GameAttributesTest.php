<?php

declare(strict_types=1);

namespace Jumpnrun\Tests\Embed;

use Brain\Monkey;
use Brain\Monkey\Functions;
use Jumpnrun\Embed\GameAttributes;
use PHPUnit\Framework\TestCase;

/**
 * Sichert die Single Source der Instanz-Attribute ab.
 *
 * Der wichtigste Fall ist das leere Feld: Elementor liefert für nicht
 * ausgefüllte Controls einen leeren String statt null. Würde der als Wert
 * durchgehen, käme im Engine-Config eine 0 an und das Spielfeld wäre auf einer
 * Elementor-Seite null Pixel breit, obwohl in den Einstellungen 960 steht.
 */
final class GameAttributesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();
        Functions\stubTranslationFunctions();
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testShortcodeDefaultsCoverEveryAttributeAndAreNull(): void
    {
        $defaults = GameAttributes::shortcodeDefaults();

        self::assertSame(
            array_keys(GameAttributes::schema()),
            array_keys($defaults),
            'Defaults und Schema dürfen nicht auseinanderlaufen.'
        );
        foreach ($defaults as $key => $value) {
            self::assertNull($value, sprintf('Default für "%s" muss null sein.', $key));
        }
    }

    public function testEverySchemaEntryMapsToAnEngineKey(): void
    {
        foreach (GameAttributes::schema() as $key => $definition) {
            self::assertArrayHasKey('engine', $definition, sprintf('"%s" braucht ein engine-Mapping.', $key));
            self::assertNotSame('', $definition['engine']);
        }
    }

    public function testNumbersAreCastToInt(): void
    {
        $result = GameAttributes::normalize(['width' => '800', 'height' => 600]);

        self::assertSame(800, $result['width']);
        self::assertSame(600, $result['height']);
    }

    public function testEmptyStringIsTreatedAsNotSet(): void
    {
        $result = GameAttributes::normalize(['width' => '', 'discount_code' => '']);

        self::assertSame([], $result, 'Leere Elementor-Felder dürfen keinen Wert erzeugen.');
    }

    public function testNullIsTreatedAsNotSet(): void
    {
        $result = GameAttributes::normalize(['width' => null, 'height' => null]);

        self::assertSame([], $result);
    }

    public function testNonNumericValueForNumberAttributeIsDropped(): void
    {
        $result = GameAttributes::normalize(['width' => 'breit']);

        self::assertSame([], $result, 'Aus "breit" darf keine 0 werden.');
    }

    public function testUnknownAttributesAreDropped(): void
    {
        $result = GameAttributes::normalize(['width' => 800, 'schummeln' => 'ja']);

        self::assertSame(['width' => 800], $result);
    }

    public function testApplyToEngineOverridesOnlyProvidedValues(): void
    {
        $engine = ['canvasWidth' => 960, 'canvasHeight' => 540, 'discountCode' => 'AUS-SETTINGS'];

        $result = GameAttributes::applyToEngine($engine, ['width' => 1200]);

        self::assertSame(1200, $result['canvasWidth']);
        self::assertSame(540, $result['canvasHeight'], 'Nicht gesetzte Attribute lassen den Admin-Wert stehen.');
        self::assertSame('AUS-SETTINGS', $result['discountCode']);
    }

    /** Der Elementor-Fall als Ganzes: leeres Formular ändert nichts. */
    public function testEmptyElementorFormLeavesAdminSettingsIntact(): void
    {
        $engine = ['canvasWidth' => 960, 'canvasHeight' => 540, 'discountCode' => 'AUS-SETTINGS'];

        $result = GameAttributes::applyToEngine($engine, [
            'width' => '',
            'height' => '',
            'discount_code' => '',
        ]);

        self::assertSame($engine, $result);
    }

    public function testDiscountCodeIsPassedThroughAsString(): void
    {
        $result = GameAttributes::applyToEngine(['discountCode' => 'ALT'], ['discount_code' => 'BURNERKING20']);

        self::assertSame('BURNERKING20', $result['discountCode']);
    }
}
