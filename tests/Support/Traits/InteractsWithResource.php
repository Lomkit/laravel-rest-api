<?php

namespace Lomkit\Rest\Tests\Support\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Client\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

trait InteractsWithResource
{
    protected function assertResourcePaginated($response, $models, Resource $resource, array $additionalFields = [], array $onlyFields = []): void
    {
        /** @var TestResponse $response */
        $response->assertStatus(200);
        $response->assertJsonStructure(
            [
                'data',
                'current_page',
                'per_page',
                'to',
                'total',
                'meta'
            ]
        );

        $this->assertEquals(
            collect($models)
                ->map(function ($model) use ($resource) {
                    return $model->only($resource->fields(App::make(RestRequest::class)));
                })
                ->when(!empty($additionalFields), function (Collection $collection) use ($additionalFields) {
                    return $collection
                        ->map(function ($item, $key) use ($additionalFields) {
                            return array_merge($item, $additionalFields[$key]);
                        });
                })->when(!empty($onlyFields), function (Collection $collection) use ($onlyFields) {
                    return $collection
                        ->map(function ($item) use ($onlyFields) {
                           return array_intersect_key($item, array_flip($onlyFields));
                        });
                })
                ->toArray(),
            $response->json('data')
        );
    }

    protected function assertResourceModel($response, $models, Resource $resource) {
        $response->assertStatus(200);

        $this->assertEquals(
            array_map(
                function($model) use ($resource) {
                    return $model->only($resource->fields(App::make(RestRequest::class)));
                },
                $models
            ),
            $response->json('data')
        );
    }

    protected function assertMutatedResponse($response, $createdModels = [], $updatedModels = []) {
        $response->assertStatus(200);

        $this->assertEquals(
            array_map(function ($model) { return $model->getKey(); }, $updatedModels),
            $response->json('updated')
        );

        foreach ($createdModels as $model) {
            $this->assertDatabaseHas($model->getTable(), $model->getAttributes());
        }
    }
}