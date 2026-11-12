<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Integer;

class RescheduleShows extends Action
{
    public ?int $maxResources = null;

    #[\Override]
    public function fields(): array
    {
        return [
            DateTime::make('at')->nullable(),
            Integer::make('notice_days')
                ->nullable()
                ->rules('between:7,365'),
            Boolean::make('notify')->default(true),
        ];
    }

    /** @param Collection<int, Show> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $show) {
            $show->update(['starts_at' => $fields->at]);
        }
    }
}
