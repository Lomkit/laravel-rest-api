<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Closure;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class RuledScopePostResource extends PostResource
{
    #[\Override]
    public function scopes(RestRequest $request): array
    {
        return [
            Scope::make('popular')->fields([
                Integer::make('min_views')->rules(static fn (string $attribute, mixed $value, Closure $fail): null => null),
            ]),
        ];
    }
}
