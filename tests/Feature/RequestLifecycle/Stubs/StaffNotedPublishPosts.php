<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PublishPosts;

class StaffNotedPublishPosts extends PublishPosts
{
    #[\Override]
    public function fields(): array
    {
        $user = $this->request()->user();

        if (! $user?->getAttribute('is_staff')) {
            return parent::fields();
        }

        return [...parent::fields(), Text::make('note')];
    }
}
