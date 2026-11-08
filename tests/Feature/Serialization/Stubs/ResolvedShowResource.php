<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;

class ResolvedShowResource extends ShowResource
{
    #[\Override]
    public function fields(): array
    {
        return [
            ...parent::fields(),
            Number::make('total')
                ->virtual()
                ->resolveUsing(fn (): string => '30.00'),
            Json::make('extras')
                ->virtual()
                ->resolveUsing(fn (): array => []),
        ];
    }
}
