<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionResponse;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Fields\Text;

class CancelShows extends Action
{
    public ActionTarget $target = ActionTarget::Resources;

    #[\Override]
    public function fields(): array
    {
        return [
            Text::make('reason')
                ->nullable()
                ->examples('Rain'),
        ];
    }

    /** @param Collection<int, Show> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $show) {
            $show->update(['status' => ShowStatus::Planned]);
        }
    }

    #[\Override]
    public function respond(ActionFields $fields, int $impacted): ActionResponse
    {
        return ActionResponse::message("{$impacted} shows cancelled")->withData(['reason' => $fields->reason]);
    }
}
