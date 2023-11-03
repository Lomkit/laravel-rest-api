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
    protected function beforeDetails(DetailsRequest $request) : void {
        //
    }

    protected function beforeSearch(SearchRequest $request) : void {
        //
    }

    protected function afterSearch(SearchRequest $request) : void {
        //
    }

    protected function beforeMutate(MutateRequest $request) : void {
        //
    }

    protected function afterMutate(MutateRequest $request) : void {
        //
    }

    protected function beforeOperate(OperateRequest $request) : void {
        //
    }

    protected function afterOperate(OperateRequest $request) : void {
        //
    }

    protected function beforeDestroy(DestroyRequest $request) : void {
        //
    }

    protected function afterDestroy(DestroyRequest $request) : void {
        //
    }

    protected function beforeRestore(RestoreRequest $request) : void {
        //
    }

    protected function afterRestore(RestoreRequest $request) : void {
        //
    }

    protected function beforeForceDestroy(ForceDestroyRequest $request) : void {
        //
    }

    protected function afterForceDestroy(ForceDestroyRequest $request) : void {
        //
    }
}