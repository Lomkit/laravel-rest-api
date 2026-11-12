<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Gallery\Artwork;

class RecordArtworks extends Action
{
    /** @var list<list<array{id: mixed, title: mixed, relations: array<string, mixed>}>> */
    public static array $runs = [];

    public int $chunkCount = 2;

    /** @param Collection<int, Artwork> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        self::$runs[] = $models
            ->map(fn (Artwork $artwork): array => [
                'id' => $artwork->getKey(),
                'title' => $artwork->getAttribute('title'),
                'relations' => array_map(fn (?Model $related): mixed => $related?->getAttribute('name'), $artwork->getRelations()),
            ])
            ->values()
            ->all();
    }
}
