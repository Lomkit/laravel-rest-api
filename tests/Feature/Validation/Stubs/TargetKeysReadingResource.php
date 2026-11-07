<?php

namespace Lomkit\Rest\Tests\Feature\Validation\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Http\Requests\OperateRequest;

class TargetKeysReadingResource extends UntypedDraftResource
{
    #[\Override]
    public function operating(Action $action, ActionFields $fields): void
    {
        $request = $this->request();

        if ($request instanceof OperateRequest) {
            $request->payload()->keys();
        }
    }
}
