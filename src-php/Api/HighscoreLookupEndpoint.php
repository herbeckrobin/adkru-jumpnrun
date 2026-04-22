<?php

declare(strict_types=1);

namespace Jumpnrun\Api;

use Jumpnrun\Db\HighscoreRepository;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Pre-Flight-Check ob ein Name schon in der Highscore-Liste steht. Wird vor
 * dem Submit aufgerufen damit der Spieler warnen kann falls der Name schon
 * vergeben ist — und ggf. einen anderen Namen waehlen, ohne dass die Session
 * bereits verbraucht ist.
 */
final class HighscoreLookupEndpoint
{
    /** @return array<string, array<string, mixed>> */
    public static function args(): array
    {
        return [
            'name' => [
                'required' => true,
                'type' => 'string',
            ],
        ];
    }

    public function handle(WP_REST_Request $request): WP_REST_Response
    {
        $raw = (string) $request->get_param('name');
        // Gleicher Sanitizer wie beim Submit — sonst findet Lookup den Namen
        // nicht, den der Server spaeter tatsaechlich speichern wuerde.
        $name = ScoreEndpoint::sanitizeName($raw, 15);
        if ($name === '') {
            return new WP_REST_Response(['exists' => false, 'score' => null], 200);
        }

        $score = (new HighscoreRepository())->findScoreByName($name);
        return new WP_REST_Response([
            'exists' => $score !== null,
            'score' => $score,
        ], 200);
    }
}
