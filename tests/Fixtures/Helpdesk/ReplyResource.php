<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class ReplyResource extends Resource
{
    public static string $model = Reply::class;

    public function fields(RestRequest $request): array
    {
        return [ID::make('id'), Integer::make('ticket_id'), Text::make('body')];
    }
}
