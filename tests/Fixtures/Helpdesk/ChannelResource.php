<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;

class ChannelResource extends Resource
{
    public static string $model = Channel::class;

    #[\Override]
    public function fields(): array
    {
        return [Text::make('code'), Text::make('name')];
    }

    #[\Override]
    public function defaultOrderBy(): array
    {
        return ['code' => 'asc'];
    }
}
