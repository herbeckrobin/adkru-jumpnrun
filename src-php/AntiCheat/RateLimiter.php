<?php

declare(strict_types=1);

namespace Jumpnrun\AntiCheat;

/**
 * IP-basiertes Rate-Limit per WP-Transients (60s Fenster).
 * Minimal: kein HMAC, kein Heartbeat (siehe ADR-004).
 */
final class RateLimiter
{
    private const WINDOW_SECONDS = 60;

    public function __construct(
        private readonly string $bucket,
        private readonly int $maxPerMinute,
    ) {
    }

    /** Gibt true zurueck wenn die Anfrage erlaubt ist, false wenn gedrosselt. */
    public function allow(string $ipHash): bool
    {
        $key = "jumpnrun_rl_{$this->bucket}_" . substr($ipHash, 0, 32);
        $current = (int) get_transient($key);

        if ($current >= $this->maxPerMinute) {
            return false;
        }

        set_transient($key, $current + 1, self::WINDOW_SECONDS);
        return true;
    }

    /** SHA-256 der Client-IP + wp_salt. Niemals die rohe IP speichern. */
    public static function ipHash(): string
    {
        $ip = self::clientIp();
        return hash('sha256', $ip . wp_salt('auth'));
    }

    private static function clientIp(): string
    {
        // Kein Vertrauen in X-Forwarded-For im Default — wer hinter Proxy
        // sitzt konfiguriert das separat.
        $raw = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
        return filter_var($raw, FILTER_VALIDATE_IP) ?: '0.0.0.0';
    }
}
