<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;

class ClausedExtension extends Extension implements RestrictsQueries
{
    /** @var (Closure(Builder<Model>): mixed)|null */
    public static ?Closure $clause = null;

    #[\Override]
    public static function name(): string
    {
        return 'claused';
    }

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        if (self::$clause !== null) {
            (self::$clause)($context->query);
        }
    }
}
