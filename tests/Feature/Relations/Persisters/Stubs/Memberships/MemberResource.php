<?php

namespace Lomkit\Rest\Tests\Feature\Relations\Persisters\Stubs\Memberships;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;

class MemberResource extends Resource
{
    public static string $model = Member::class;

    #[\Override]
    public function fields(): array
    {
        return [ID::make('id'), Text::make('name')];
    }
}
