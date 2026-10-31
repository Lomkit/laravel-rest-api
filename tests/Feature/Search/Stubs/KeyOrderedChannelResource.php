<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Channel;

class KeyOrderedChannelResource extends Resource
{
    public static string $model = Channel::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('code'), Text::make('name')];
    }
}
