<?php

namespace Lomkit\Rest\Tests\Support\Http\Controllers;

use Illuminate\Support\Facades\Cache;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\DestroyRequest;
use Lomkit\Rest\Http\Requests\DetailsRequest;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelWithHooksResource;

class ModelHooksController extends Controller
{
    public static $resource = ModelWithHooksResource::class;

    protected function beforeDetails(DetailsRequest $request): void
    {
        Cache::put(
            'before-details',
            true,
            5
        );
    }

    protected function beforeSearch(SearchRequest $request): void
    {
        Cache::put(
            'before-search',
            true,
            5
        );
    }

    protected function afterSearch(SearchRequest $request): void
    {
        Cache::put(
            'after-search',
            true,
            5
        );
    }

    protected function beforeMutate(MutateRequest $request): void
    {
        Cache::put(
            'before-mutate',
            true,
            5
        );
    }

    protected function afterMutate(MutateRequest $request): void
    {
        Cache::put(
            'after-mutate',
            true,
            5
        );
    }

    protected function beforeOperate(OperateRequest $request): void
    {
        Cache::put(
            'before-operate',
            true,
            5
        );
    }

    protected function afterOperate(OperateRequest $request): void
    {
        Cache::put(
            'after-operate',
            true,
            5
        );
    }

    protected function beforeDestroy(DestroyRequest $request): void
    {
        Cache::put(
            'before-destroy',
            true,
            5
        );
    }

    protected function afterDestroy(DestroyRequest $request): void
    {
        Cache::put(
            'after-destroy',
            true,
            5
        );
    }

    protected function beforeRestore(RestoreRequest $request): void
    {
        Cache::put(
            'before-restore',
            true,
            5
        );
    }

    protected function afterRestore(RestoreRequest $request): void
    {
        Cache::put(
            'after-restore',
            true,
            5
        );
    }

    protected function beforeForceDestroy(ForceDestroyRequest $request): void
    {
        Cache::put(
            'before-force-destroy',
            true,
            5
        );
    }

    protected function afterForceDestroy(ForceDestroyRequest $request): void
    {
        Cache::put(
            'after-force-destroy',
            true,
            5
        );
    }
}
