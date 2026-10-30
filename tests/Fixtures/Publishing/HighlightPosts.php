<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;

class HighlightPosts extends Instruction
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Boolean::make('featured')->default(true), Integer::make('min_views')->nullable()];
    }

    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        app(ReceivedFields::class)->record('handle', $fields);

        $query->where('featured', $fields['featured']);

        if (isset($fields['min_views'])) {
            $query->where('views', '>=', $fields['min_views']);
        }
    }
}
