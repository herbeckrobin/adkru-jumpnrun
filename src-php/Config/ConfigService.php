<?php

declare(strict_types=1);

namespace Jumpnrun\Config;

/**
 * Config-Provider. v0.2.0 liefert nur Defaults.
 * In v0.3.0 kommt Admin-Override aus wp_options dazu (siehe Plan Milestone B).
 */
final class ConfigService
{
    /**
     * @return array{rateLimitSessionPerMin:int, rateLimitScorePerMin:int, minSessionDurationSec:int}
     */
    public static function antiCheatLimits(): array
    {
        return [
            'rateLimitSessionPerMin' => 10,
            'rateLimitScorePerMin' => 30,
            'minSessionDurationSec' => 5,
        ];
    }

    /** @return array{playerNameMaxLength:int, discountCode:string, discountLevel:int} */
    public static function gameDefaults(): array
    {
        return [
            'playerNameMaxLength' => 15,
            'discountCode' => 'BURNERKING20',
            'discountLevel' => 3,
        ];
    }
}
