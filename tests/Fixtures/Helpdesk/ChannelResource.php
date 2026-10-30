<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class ChannelResource extends Resource
{
    public static string $model = Channel::class;

    public function fields(RestRequest $request): array
    {
        return [Text::make('code'), Text::make('name')];
    }

    public function defaultOrderBy(RestRequest $request): array
    {
        return ['code' => 'asc'];
    }
}
