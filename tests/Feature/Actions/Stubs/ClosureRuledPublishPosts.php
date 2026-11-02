<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Closure;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PublishPosts;

class ClosureRuledPublishPosts extends PublishPosts
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Text::make('reason')->rules(static fn (string $attribute, mixed $value, Closure $fail): null => null)];
    }
}
