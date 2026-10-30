<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class AttachPayload extends Action
{
    public function fields(RestRequest $request): array
    {
        return [Text::make('reason'), Json::make('payload')];
    }

    /**
     * @param  array<string, mixed>  $fields
     * @param  Collection<int, mixed>  $models
     */
    public function handle(array $fields, Collection $models): void
    {
        app(ReceivedFields::class)->record('handle', $fields);
    }
}
