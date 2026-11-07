<?php

namespace Lomkit\Rest\Tests\Support\Traits;

use Illuminate\Support\Collection;
use Illuminate\Testing\TestResponse;
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
                'meta',
            ]
        );

        $this->assertEquals(
            collect($models)
                ->map(function ($model) use ($resource) {
                    return $model->only(
                        $resource
                            ->definition()
                            ->fields()
                            ->selectable()
                            ->names()
                    );
                })
                ->when(! empty($additionalFields), function (Collection $collection) use ($additionalFields) {
                    return $collection
                        ->map(function ($item, $key) use ($additionalFields) {
                            return array_merge($item, $additionalFields[$key]);
                        });
                })->when(! empty($onlyFields), function (Collection $collection) use ($onlyFields) {
                    return $collection
                        ->map(function ($item) use ($onlyFields) {
                            return array_intersect_key($item, array_flip($onlyFields));
                        });
                })
                ->toArray(),
            $response->json('data')
        );
    }
}
