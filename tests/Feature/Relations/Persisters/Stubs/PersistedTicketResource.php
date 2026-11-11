<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs;

use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Feature\Relations\Stubs\WeightedPivot;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class PersistedTicketResource extends TicketResource
{
    public static string $persister = RecordingPivots::class;

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsToMany::make('tags', PersistedTagResource::class)
                ->pivotFields(WeightedPivot::fields())
                ->abilities(attach: 'attachTag', detach: 'detachTag')
                ->persistedBy(self::$persister),
            MorphToMany::make('labels', PersistedTagResource::class)
                ->pivotFields(WeightedPivot::fields())
                ->persistedBy(self::$persister),
        ];
    }
}
