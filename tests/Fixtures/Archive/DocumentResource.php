<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

class DocumentResource extends Resource
{
    public static $model = Document::class;

    public function fields(RestRequest $request): array
    {
        return ['id', 'title', 'archived'];
    }

    public function actions(RestRequest $request): array
    {
        return [ArchiveDocumentsAction::make()];
    }
}
