<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Lomkit\Rest\Relations\MorphTo;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;

class PerimeterLabelResource extends LabelResource
{
    use HidesNamedRows;

    #[\Override]
    public function relations(): array
    {
        return [MorphTo::make('labelable', [PerimeterBookResource::class, PerimeterShelfResource::class])];
    }

    protected static function nameColumn(): string
    {
        return 'labels.text';
    }
}
