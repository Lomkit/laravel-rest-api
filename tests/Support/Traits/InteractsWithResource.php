<?php

namespace Lomkit\Rest\Tests\Support\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\App;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;

trait InteractsWithResource
{
    protected function assertResourcePaginated($response, $models, Resource $resource, array $selectFields = []): void
    {
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

        $expected = [
            'data' => collect(
                $resource::newResponse()->responsable(new LengthAwarePaginator($models, 1, 1))->toResponse(request())->items()
            )->map(function ($model) use ($resource) {
                return $model->only(
                    empty($selectFields) ? $resource->exposedFields(App::make(RestRequest::class)) : $selectFields
                );
            })
                ->toArray()
        ];

        $response->assertJson($expected, true);
    }
}