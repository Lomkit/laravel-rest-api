<?php

namespace Lomkit\Rest\Authorization;

use Lomkit\Rest\Exceptions\AuthorizationDenied;

/** @internal */
final readonly class Denials
{
    /**
     * @param  list<Check>  $checks
     * @param  list<Decision>  $decisions
     */
    public static function enforce(array $checks, array $decisions): void
    {
        $denials = [];

        foreach ($decisions as $index => $decision) {
            if (! $decision->allowed) {
                $denials[] = Denial::of($checks[$index], $decision);
            }
        }

        if ($denials !== []) {
            throw new AuthorizationDenied($denials);
        }
    }
}
