<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;

class FieldCountingPostResource extends PostResource
{
    #[\Override]
    public function actions(RestRequest $request): array
    {
        return [
            CountedPublishPosts::make(),
            QueuedCountedPublishPosts::make(),
            BatchedCountedPublishPosts::make(),
            TargetedCountedPublishPosts::make(),
            StandaloneCountedPublishPosts::make(),
            QueuedClosureRuledPublishPosts::make(),
            BatchedClosureRuledPublishPosts::make(),
        ];
    }
}
