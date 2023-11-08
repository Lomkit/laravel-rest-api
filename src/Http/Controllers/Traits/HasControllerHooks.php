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
     *
     * @param DetailsRequest $request
     *
     * @return void
     */
    protected function beforeDetails(DetailsRequest $request): void
    {
        //
    }

    /**
     * Executed before search.
     *
     * @param SearchRequest $request
     *
     * @return void
     */
    protected function beforeSearch(SearchRequest $request): void
    {
        //
    }

    /**
     * Executed after search.
     *
     * @param SearchRequest $request
     *
     * @return void
     */
    protected function afterSearch(SearchRequest $request): void
    {
        //
    }

    /**
     * Executed before mutate.
     *
     * @param MutateRequest $request
     *
     * @return void
     */
    protected function beforeMutate(MutateRequest $request): void
    {
        //
    }

    /**
     * Executed after mutate.
     *
     * @param MutateRequest $request
     *
     * @return void
     */
    protected function afterMutate(MutateRequest $request): void
    {
        //
    }

    /**
     * Executed before operate.
     *
     * @param OperateRequest $request
     *
     * @return void
     */
    protected function beforeOperate(OperateRequest $request): void
    {
        //
    }

    /**
     * Executed after operate.
     *
     * @param OperateRequest $request
     *
     * @return void
     */
    protected function afterOperate(OperateRequest $request): void
    {
        //
    }

    /**
     * Executed before destroy.
     *
     * @param DestroyRequest $request
     *
     * @return void
     */
    protected function beforeDestroy(DestroyRequest $request): void
    {
        //
    }

    /**
     * Executed after destroy.
     *
     * @param DestroyRequest $request
     *
     * @return void
     */
    protected function afterDestroy(DestroyRequest $request): void
    {
        //
    }

    /**
     * Executed before restore.
     *
     * @param RestoreRequest $request
     *
     * @return void
     */
    protected function beforeRestore(RestoreRequest $request): void
    {
        //
    }

    /**
     * Executed after restore.
     *
     * @param RestoreRequest $request
     *
     * @return void
     */
    protected function afterRestore(RestoreRequest $request): void
    {
        //
    }

    /**
     * Executed before force destroy.
     *
     * @param ForceDestroyRequest $request
     *
     * @return void
     */
    protected function beforeForceDestroy(ForceDestroyRequest $request): void
    {
        //
    }

    /**
     * Executed after force destroy.
     *
     * @param ForceDestroyRequest $request
     *
     * @return void
     */
    protected function afterForceDestroy(ForceDestroyRequest $request): void
    {
        //
    }
}
