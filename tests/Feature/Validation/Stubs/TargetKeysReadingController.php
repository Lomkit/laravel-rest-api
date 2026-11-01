<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\OperateRequest;

class TargetKeysReadingController extends Controller
{
    public static string $resource = UntypedDraftResource::class;

    #[\Override]
    protected function beforeOperate(OperateRequest $request): void
    {
        $request->targetKeys();
    }
}
