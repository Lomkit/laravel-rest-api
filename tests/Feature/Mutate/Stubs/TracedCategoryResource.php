<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Mutations\MutationNode;

class TracedCategoryResource extends PricedCategoryResource
{
    /** @var list<array{array<array-key, mixed>|null, list<array<array-key, mixed>|null>}> */
    public static array $entries = [];

    #[\Override]
    public function mutating(MutationNode $entry, Model $model): void
    {
        $featured = $entry->relations['featuredProducts'] ?? [];

        self::$entries[] = [$entry->attributes, array_map(fn (MutationNode $node): ?array => $node->pivot, is_array($featured) ? $featured : [$featured])];
    }
}
