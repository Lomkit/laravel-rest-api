<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Requests\RestRequest;

class ArchivingDocumentResource extends TracingDocumentResource
{
    #[\Override]
    public function performDelete(RestRequest $request, Model $model): void
    {
        $model->forceFill(['archived' => true])->save();

        parent::performDelete($request, $model);
    }

    #[\Override]
    public function performRestore(RestRequest $request, Model $model): void
    {
        $model->forceFill(['archived' => true])->save();

        parent::performRestore($request, $model);
    }

    #[\Override]
    public function performForceDelete(RestRequest $request, Model $model): void
    {
        $model->forceFill(['archived' => true])->save();

        parent::performForceDelete($request, $model);
    }
}
