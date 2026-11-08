<?php

namespace Lomkit\Rest\Tests\Fixtures\Festival;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionResponse;
use Lomkit\Rest\Actions\ActionTarget;

class ImportShows extends Action
{
    public ActionTarget $target = ActionTarget::None;

    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void {}

    #[\Override]
    public function respond(ActionFields $fields, int $impacted): ActionResponse
    {
        return ActionResponse::data(['imported' => 0]);
    }
}
