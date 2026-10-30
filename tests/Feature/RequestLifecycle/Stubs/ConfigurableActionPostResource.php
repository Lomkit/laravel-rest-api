<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Closure;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ConfigurableActionPostResource extends PostResource
{
    #[\Override]
    public function actions(RestRequest $request): array
    {
        return [
            ...parent::actions($request),
            ConfigurableAction::make([
                Text::make('reason')->rules(static fn (string $attribute, mixed $value, Closure $fail): null => null),
            ]),
        ];
    }
}
