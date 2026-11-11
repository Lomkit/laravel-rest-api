<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Tests\Fixtures\Sales\OrderResource;

class AddressedOrderResource extends OrderResource
{
    public static string $model = AddressedOrder::class;

    public static bool $updatable = true;

    /** @var list<string> */
    public static array $hooks = [];

    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            Text::make('delivery_address')
                ->inputOnly()
                ->rules('max:120')
                ->updatable(self::$updatable),
        ];
    }

    #[\Override]
    public function relations(): array
    {
        return [HasMany::make('teamOrders', self::class)];
    }

    #[\Override]
    public function mutating(MutationNode $entry, Model $model): void
    {
        self::$hooks[] = 'mutating '.$entry->operation->value.' '.json_encode($entry->attributes);
    }

    #[\Override]
    public function mutated(MutationNode $entry, Model $model): void
    {
        self::$hooks[] = 'mutated '.$entry->operation->value.' '.json_encode($entry->attributes);
    }
}
