<?php

declare(strict_types=1);

namespace Jumpnrun\Api;

use Jumpnrun\AntiCheat\RateLimiter;
use Jumpnrun\Config\ConfigService;
use Jumpnrun\Db\SessionRepository;
use WP_Error;
use WP_REST_Response;

final class SessionEndpoint
{
    public function handle(): WP_REST_Response|WP_Error
    {
        $limits = ConfigService::antiCheatLimits();
        $ipHash = RateLimiter::ipHash();
        $limiter = new RateLimiter('session', $limits['rateLimitSessionPerMin']);

        if (!$limiter->allow($ipHash)) {
            return new WP_Error('rate_limited', 'Zu viele Anfragen.', ['status' => 429]);
        }

        $id = (new SessionRepository())->create($ipHash);
        return new WP_REST_Response(['sessionId' => $id], 201);
    }
}
