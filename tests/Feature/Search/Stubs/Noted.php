<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class Noted extends Instruction
{
    #[\Override]
    public function fields(): array
    {
        return [Integer::make('min_views')->nullable()];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        if ($fields->min_views !== null) {
            $query->where('views', '>=', $fields->min_views);
        }
    }

    #[\Override]
    public function searchMeta(InstructionFields $fields): array
    {
        if ($fields->min_views === null) {
            return [];
        }

        return ['notice' => "Lists the posts with {$fields->min_views} views or more."];
    }
}
