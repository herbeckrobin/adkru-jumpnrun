<?php

declare(strict_types=1);

namespace Jumpnrun\Api;

use Jumpnrun\Db\HighscoreRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * GET /rank?score=N — liefert den vorausichtlichen Rang fuer einen Score,
 * der noch nicht gespeichert wurde. Wird direkt nach Game-Over abgefragt
 * damit "Du bist Platz X" ohne Save-Klick angezeigt werden kann.
 *
 * Tie-Breaker ohne Name: bei Gleichstand mit existierenden Eintraegen wird
 * der best moegliche Rang geliefert (siehe HighscoreRepository::previewRankFor).
 */
final class RankPreviewEndpoint
{
    /**
     * @return array<string, array<string, mixed>>
     */
    public static function args(): array
    {
        return [
            'score' => [
                'required' => true,
                'type' => 'integer',
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $score = (int) $request->get_param('score');
        $repo = new HighscoreRepository();
        return new WP_REST_Response([
            'rank' => $repo->previewRankFor($score),
            'totalEntries' => $repo->totalEntries(),
        ], 200);
    }
}
