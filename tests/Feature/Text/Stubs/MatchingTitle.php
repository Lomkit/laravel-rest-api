<?php

namespace Lomkit\Rest\Tests\Feature\Text\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;

class MatchingTitle extends Instruction
{
    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [Text::make('words')];
    }

    #[\Override]
    public function handle(array $fields, Builder $query): void
    {
        $query->whereFullText('title', $fields['words']);
    }
}
