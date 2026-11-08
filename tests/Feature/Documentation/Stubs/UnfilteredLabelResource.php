<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Festival\Label;

class UnfilteredLabelResource extends Resource
{
    public static string $model = Label::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id')->filterable(false), Text::make('name')->filterable(false)];
    }
}
