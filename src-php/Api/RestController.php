<?php

declare(strict_types=1);

namespace Jumpnrun\Api;

final class RestController
{
    public const NAMESPACE = 'jumpnrun/v1';

    public function registerRoutes(): void
    {
        register_rest_route(self::NAMESPACE, '/session', [
            'methods' => 'POST',
            'callback' => [new SessionEndpoint(), 'handle'],
            'permission_callback' => '__return_true',
        ]);

        register_rest_route(self::NAMESPACE, '/score', [
            'methods' => 'POST',
            'callback' => [new ScoreEndpoint(), 'handle'],
            'permission_callback' => '__return_true',
            'args' => ScoreEndpoint::args(),
        ]);

        register_rest_route(self::NAMESPACE, '/highscores', [
            'methods' => 'GET',
            'callback' => [new HighscoreEndpoint(), 'handle'],
            'permission_callback' => '__return_true',
            'args' => HighscoreEndpoint::args(),
        ]);
    }
}
