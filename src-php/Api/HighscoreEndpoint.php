<?php

declare(strict_types=1);

namespace Jumpnrun\Api;

use Jumpnrun\Db\HighscoreRepository;
use WP_REST_Request;
use WP_REST_Response;

/** GET /highscores — liefert die Top-Liste als JSON. */
final class HighscoreEndpoint
{
    /**
     * Argument-Specs fuer register_rest_route inkl. Range + Sanitize.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function args(): array
    {
        return [
            'limit' => [
                'required' => false,
                'type' => 'integer',
                'default' => 10,
                'sanitize_callback' => 'absint',
            ],
        ];
    }

    /** Gibt die Top-N-Scores als JSON-Response zurueck. */
    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $limit = (int) $request->get_param('limit');
        $rows = (new HighscoreRepository())->topN($limit);
        return new WP_REST_Response(['data' => $rows], 200);
    }
}
