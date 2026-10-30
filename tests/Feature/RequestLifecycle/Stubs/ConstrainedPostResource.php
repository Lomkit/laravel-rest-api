<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class ConstrainedPostResource extends PostResource
{
    #[\Override]
    public function relations(RestRequest $request): array
    {
        return [
            BelongsTo::make('author', AuthorResource::class)
                ->requiredOnCreation(AuthorRequirement::class.'::onCreation')
                ->requiredOnUpdate([new AuthorRequirement, 'onUpdate'])
                ->prohibitedOnUpdate(fn (RestRequest $request): bool => $request->boolean('detach')),
        ];
    }
}
