<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

class ViewerStageResource extends StageResource
{
    #[\Override]
    public function fields(): array
    {
        if ($this->request()->user() === null) {
            return parent::fields();
        }

        return [...parent::fields(), Text::make('internal_note')];
    }
}
