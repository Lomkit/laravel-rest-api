<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;

class TracingDocumentController extends Controller
{
    public static string $resource = TracingDocumentResource::class;

    #[\Override]
    protected function beforeDestroy(DestroyRequest $request): void
    {
        DeletionTrace::$lines[] = 'beforeDestroy';
    }

    #[\Override]
    protected function afterDestroy(DestroyRequest $request): void
    {
        DeletionTrace::$lines[] = 'afterDestroy';
    }

    #[\Override]
    protected function beforeRestore(RestoreRequest $request): void
    {
        DeletionTrace::$lines[] = 'beforeRestore';
    }

    #[\Override]
    protected function afterRestore(RestoreRequest $request): void
    {
        DeletionTrace::$lines[] = 'afterRestore';
    }

    #[\Override]
    protected function beforeForceDestroy(ForceDestroyRequest $request): void
    {
        DeletionTrace::$lines[] = 'beforeForceDestroy';
    }

    #[\Override]
    protected function afterForceDestroy(ForceDestroyRequest $request): void
    {
        DeletionTrace::$lines[] = 'afterForceDestroy';
    }
}
