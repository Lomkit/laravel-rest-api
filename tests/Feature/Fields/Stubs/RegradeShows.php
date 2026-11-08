<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowLevel;

class RegradeShows extends Action
{
    public ActionTarget $target = ActionTarget::Resources;

    #[\Override]
    public function fields(): array
    {
        return [
            Enum::make('level', ShowLevel::class)
                ->rules('required')
                ->offered(fn (ShowLevel $level): bool => $level !== ShowLevel::Headline),
        ];
    }

    /** @param Collection<int, Show> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        foreach ($models as $show) {
            $show->update(['level' => $fields->level]);
        }
    }
}
