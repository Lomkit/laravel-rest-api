<?php

namespace Lomkit\Rest\Http\Controllers\Traits;

use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;

trait HasControllerHooks
{
    /**
     * Executed before details.
     */
    protected function beforeDetails(DetailsRequest $request): void
    {
        //
    }

    /**
     * Executed before search.
     */
    protected function beforeSearch(SearchRequest $request): void
    {
        //
    }

    /**
     * Executed after search.
     */
    protected function afterSearch(SearchRequest $request): void
    {
        //
    }

    /**
     * Executed before mutate.
     */
    protected function beforeMutate(MutateRequest $request): void
    {
        //
    }

    /**
     * Executed after mutate.
     */
    protected function afterMutate(MutateRequest $request): void
    {
        //
    }

    /**
     * Executed before operate.
     */
    protected function beforeOperate(OperateRequest $request): void
    {
        //
    }

    /**
     * Executed after operate.
     */
    protected function afterOperate(OperateRequest $request): void
    {
        //
    }

    /**
     * Executed before destroy.
     */
    protected function beforeDestroy(DestroyRequest $request): void
    {
        //
    }

    /**
     * Executed after destroy.
     */
    protected function afterDestroy(DestroyRequest $request): void
    {
        //
    }

    /**
     * Executed before restore.
     */
    protected function beforeRestore(RestoreRequest $request): void
    {
        //
    }

    /**
     * Executed after restore.
     */
    protected function afterRestore(RestoreRequest $request): void
    {
        //
    }

    /**
     * Executed before force destroy.
     */
    protected function beforeForceDestroy(ForceDestroyRequest $request): void
    {
        //
    }

    /**
     * Executed after force destroy.
     */
    protected function afterForceDestroy(ForceDestroyRequest $request): void
    {
        //
    }
}
