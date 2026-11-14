<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorResource;

class AliasFieldAuthorResource extends PathAuthorResource
{
    #[\Override]
    public function fields(): array
    {
        return [...parent::fields(), Text::make('first__total')->virtual()];
    }
}
