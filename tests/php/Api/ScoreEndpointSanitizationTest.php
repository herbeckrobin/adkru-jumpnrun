<?php

declare(strict_types=1);

namespace Jumpnrun\Tests\Api;

use Jumpnrun\Api\ScoreEndpoint;
use PHPUnit\Framework\TestCase;

final class ScoreEndpointSanitizationTest extends TestCase
{
    public function testRemovesHtmlTags(): void
    {
        $clean = ScoreEndpoint::sanitizeName('<script>Evil</script>', 32);
        self::assertSame('scriptEvilscript', $clean);
        self::assertStringNotContainsString('<', $clean);
        self::assertStringNotContainsString('>', $clean);
    }

    public function testKeepsUnicodeLetters(): void
    {
        self::assertSame('Björn', ScoreEndpoint::sanitizeName('Björn', 15));
        self::assertSame('松本', ScoreEndpoint::sanitizeName('松本', 15));
    }

    public function testTrimsToMaxLength(): void
    {
        $long = str_repeat('a', 100);
        self::assertSame(15, mb_strlen(ScoreEndpoint::sanitizeName($long, 15)));
    }

    public function testStripsQuotesAndSemicolons(): void
    {
        // Defense-in-depth: SQL-Injection verhindert Prepared Statements,
        // der Sanitizer entfernt nur XSS-/Display-relevante Zeichen.
        $clean = ScoreEndpoint::sanitizeName("Robert'); DROP", 32);
        self::assertStringNotContainsString("'", $clean);
        self::assertStringNotContainsString(';', $clean);
        self::assertStringNotContainsString(')', $clean);
    }

    public function testTrimsWhitespace(): void
    {
        self::assertSame('Robin', ScoreEndpoint::sanitizeName('   Robin   ', 15));
    }

    public function testAllowsSpacesAndDashesAndUnderscores(): void
    {
        self::assertSame('Jack_The-Hero', ScoreEndpoint::sanitizeName('Jack_The-Hero', 15));
    }
}
