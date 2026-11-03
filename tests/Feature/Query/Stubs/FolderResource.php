<?php

namespace Lomkit\Rest\Tests\Feature\Query\Stubs;

use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class FolderResource extends Resource
{
    public static string $model = Folder::class;

    #[\Override]
    public function fields(RestRequest $request): array
    {
        return [ID::make('id')->keyType('uuid')];
    }
}
