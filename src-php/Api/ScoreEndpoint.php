<?php

declare(strict_types=1);

namespace Jumpnrun\Api;

use Jumpnrun\AntiCheat\RateLimiter;
use Jumpnrun\Config\ConfigService;
use Jumpnrun\Db\HighscoreRepository;
use Jumpnrun\Db\SessionRepository;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class ScoreEndpoint
{
    /** @return array<string, array<string, mixed>> */
    public static function args(): array
    {
        return [
            'sessionId' => [
                'required' => true,
                'type' => 'string',
                'validate_callback' => static fn ($v): bool => is_string($v) && (bool) wp_is_uuid($v),
            ],
            'name' => [
                'required' => true,
                'type' => 'string',
                'sanitize_callback' => 'sanitize_text_field',
            ],
            'score' => [
                'required' => true,
                'type' => 'integer',
                'validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v >= 0 && (int) $v <= 100000,
            ],
            'level' => [
                'required' => true,
                'type' => 'integer',
                'validate_callback' => static fn ($v): bool => is_numeric($v) && (int) $v >= 1 && (int) $v <= 99,
            ],
        ];
    }

    public function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $limits = ConfigService::antiCheatLimits();
        $nameMax = ConfigService::gameDefaults()['playerNameMaxLength'];

        $ipHash = RateLimiter::ipHash();
        $limiter = new RateLimiter('score', $limits['rateLimitScorePerMin']);
        if (!$limiter->allow($ipHash)) {
            return new WP_Error('rate_limited', 'Zu viele Anfragen.', ['status' => 429]);
        }

        $sessionId = (string) $request->get_param('sessionId');
        $name = self::sanitizeName((string) $request->get_param('name'), $nameMax);
        $score = (int) $request->get_param('score');
        $level = (int) $request->get_param('level');

        if ($name === '') {
            $name = 'Spieler';
        }

        // Atomarer Uebergang active -> submitted + Min-Duration-Check in EINEM Query.
        // Bei 0 affected rows: unbekannt, schon eingereicht oder zu schnell.
        $ok = (new SessionRepository())->markSubmitted($sessionId, $limits['minSessionDurationSec']);
        if (!$ok) {
            return new WP_Error(
                'session_rejected',
                'Session abgelaufen, schon eingereicht oder Spielzeit zu kurz.',
                ['status' => 409]
            );
        }

        $repo = new HighscoreRepository();
        $result = $repo->upsert($name, $score, $level);

        // Rank zaehlt den gespeicherten (besten) Score, nicht zwingend den eingereichten.
        // Name mitgeben, damit Ties nach demselben updated_at-Kriterium wie topN aufgeloest werden.
        $rank = $repo->rankOf($name, $result['storedScore']);

        return new WP_REST_Response([
            'rank' => $rank,
            'personalBest' => $result['personalBest'],
            'previousBest' => $result['previousBest'],
            'storedScore' => $result['storedScore'],
            'submittedScore' => $score,
            'name' => $name,
        ], 200);
    }

    public static function sanitizeName(string $raw, int $maxLen): string
    {
        $clean = preg_replace('/[^\p{L}\p{N} _-]/u', '', $raw) ?? '';
        return mb_substr(trim($clean), 0, $maxLen);
    }
}
