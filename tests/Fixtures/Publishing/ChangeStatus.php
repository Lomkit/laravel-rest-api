<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class ChangeStatus extends Action
{
    public function fields(RestRequest $request): array
    {
        return [
            Enum::make('status', PostStatus::class)->rules('required'),
            DateTime::make('at')->rules('required_if:status,published'),
        ];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, Post>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        app(ReceivedFields::class)->record('handle', $fields);

        foreach ($models as $post) {
            $post->update(['status' => $fields['status'], 'published_at' => $fields['at'] ?? null]);
        }
    }
}
