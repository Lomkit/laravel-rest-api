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
    protected function assertResourcePaginated($response, $models, Resource $resource, array $additionalFields = []): void
    {
        /** @var TestResponse $response */
        $response->assertStatus(200);
        $response->assertJsonStructure(
            [
                'data',
                'current_page',
                'next_page_url',
                'path',
                'per_page',
                'prev_page_url',
                'to',
                'total'
            ]
        );

        $this->assertEquals(
            collect(
                $resource::newResponse()->responsable(new LengthAwarePaginator($models, 1, 1))->toResponse(request())->items()
            )->map(function ($model) use ($resource) {
                return $model->only($resource->exposedFields(App::make(RestRequest::class)));
            })->when(!empty($additionalFields), function (Collection $collection) use ($additionalFields) {
                return $collection
                    ->map(function ($item, $key) use ($additionalFields) {
                        return array_merge($item, $additionalFields[$key]);
                    });
            })->toArray(),
            $response->json('data')
        );
    }
}