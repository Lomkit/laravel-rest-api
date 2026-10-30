<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class InStatus extends Instruction
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [
            Enum::make('status', PostStatus::class)->rules('required'),
            Date::make('since')->rules('required_if:status,published'),
        ];
    }

    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        app(ReceivedFields::class)->record('handle', $fields);

        $query->where('status', $fields['status']);

        if (isset($fields['since'])) {
            $query->where('published_on', '>=', $fields['since']);
        }
    }
}
