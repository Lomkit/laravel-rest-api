<?php

namespace Lomkit\Rest\Tests\Feature\Labels\Stubs;

use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Tests\Fixtures\Publishing\ChangeStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;

class LabelledChangeStatus extends ChangeStatus
{
    #[\Override]
    public function fields(): array
    {
        return [
            Enum::make('status', PostStatus::class)
                ->rules('required')
                ->label('publication status')
                ->messages(['required' => 'Choose a :attribute.']),
            DateTime::make('at')
                ->rules('required_if:status,published')
                ->label('release date'),
        ];
    }
}
