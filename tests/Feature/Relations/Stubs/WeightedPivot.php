<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Stubs;

use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;

final class WeightedPivot
{
    /** @return list<Field> */
    public static function fields(): array
    {
        return [
            Integer::make('position')->nullable(),
            Integer::make('weight')
                ->rules('min:0')
                ->creationRules('required'),
            Text::make('added_by')
                ->rules('required_with:note')
                ->updatable(false)
                ->selectable(false),
            Text::make('note')->writeOnly(),
            DateTime::make('created_at')->readonly(),
        ];
    }
}
