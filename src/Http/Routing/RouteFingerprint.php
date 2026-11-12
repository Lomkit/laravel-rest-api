<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Routing\Route;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Metrics\Metric;

final readonly class RouteFingerprint
{
    public const string ACTION_KEY = 'rest_declaration';

    public static function onRoute(mixed $route): ?string
    {
        $fingerprint = $route instanceof Route ? $route->getAction(self::ACTION_KEY) : null;

        return is_string($fingerprint) ? $fingerprint : null;
    }

    public static function check(?string $fingerprint, Action|Metric $declaration, DeclarationKind $kind): void
    {
        if ($fingerprint === null) {
            return;
        }

        if (DeclaredRoute::ofInstance($declaration)->fingerprint() === $fingerprint) {
            return;
        }

        throw $kind->staleRoute($declaration::class);
    }
}
