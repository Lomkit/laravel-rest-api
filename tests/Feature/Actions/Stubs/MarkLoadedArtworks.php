<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artwork;

class MarkLoadedArtworks extends Instruction
{
    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $query->afterQuery(function (Collection $artworks): void {
            $artworks->each(function (Artwork $artwork): void {
                $artwork->setAttribute('title', "{$artwork->getAttribute('title')} (loaded)");
            });
        });
    }
}
