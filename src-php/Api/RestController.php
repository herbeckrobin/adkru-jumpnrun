<?php

declare(strict_types=1);

namespace Jumpnrun\Api;

/** Registriert alle REST-Routen unter dem Namespace jumpnrun/v1. */
final class RestController
{
    public const NAMESPACE = 'jumpnrun/v1';

    /** Haengt die Endpoint-Handler an die REST-API. */
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

        register_rest_route(self::NAMESPACE, '/highscore/lookup', [
            'methods' => 'GET',
            'callback' => [new HighscoreLookupEndpoint(), 'handle'],
            'permission_callback' => '__return_true',
            'args' => HighscoreLookupEndpoint::args(),
        ]);
    }
}
