<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

final class TotalsComputationException extends LogicException
{
    public static function uncapturedQuery(string $resource, string $engine): self
    {
        return new self("The engine [{$engine}] did not run the query callback of the search of the resource [{$resource}], so the package cannot compute the totals that the search asks for. Check that the engine builds its query through Scout's query() callback.");
    }

    public static function untotalledProfile(string $resource, string $profile): self
    {
        return new self("The search of the resource [{$resource}] asks for totals, but the engine profile [{$profile}] computes none or the resource declares none on its ScoutEngine. Declare them with groupable() or measurable(), on an engine that computes totals.");
    }
}
