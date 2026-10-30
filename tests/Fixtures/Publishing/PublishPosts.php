<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class PublishPosts extends Action
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [DateTime::make('at')->nullable(), Boolean::make('notify')->default(true)];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Post>  $models
     */
    #[\Override]
    public function handle(array $fields, Collection $models): void
    {
        app(ReceivedFields::class)->record('handle', $fields);

        foreach ($models as $post) {
            $post->update(['status' => PostStatus::Published, 'published_at' => $fields['at'] ?? null]);
        }
    }
}
