<?php

declare(strict_types=1);

namespace Jumpnrun\Api;

use Jumpnrun\Db\HighscoreRepository;
use WP_REST_Request;
use WP_REST_Response;

final class HighscoreEndpoint
{
    /** @return array<string, array<string, mixed>> */
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

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $limit = (int) $request->get_param('limit');
        $rows = (new HighscoreRepository())->topN($limit);
        return new WP_REST_Response(['data' => $rows], 200);
    }
}
