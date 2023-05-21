<?php

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Tests\Support\Http\Controllers\ModelController;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class QueryBuilderTest extends \Lomkit\Rest\Tests\TestCase
{
    public function test_building_query_for_empty_params()
    {
        $queryBuilderMock = Mockery::mock(Builder::class, [new ModelResource()])->makePartial();

        $queryBuilderMock->shouldReceive('applySorts')->with([])->never();
        $queryBuilderMock->shouldReceive('applyScopes')->with([])->never();
        $queryBuilderMock->shouldReceive('applyFilters')->with([])->never();
        $queryBuilderMock->shouldReceive('applyIncludes')->with([])->never();

        $queryBuilderMock
            ->search();
    }

    public function test_building_query_for_sorts()
    {
        $queryBuilderMock = Mockery::mock(Builder::class, [new ModelResource()])->makePartial();

        $queryBuilderMock->shouldReceive('applySorts')->with(
            [
                ['field' => 'test']
            ]
        )->once();
        $queryBuilderMock->shouldReceive('applyScopes')->with([])->never();
        $queryBuilderMock->shouldReceive('applyFilters')->with([])->never();
        $queryBuilderMock->shouldReceive('applyIncludes')->with([])->never();

        $queryBuilderMock
            ->search([
                'sorts' => [
                    ['field' => 'test']
                ]
            ]);
    }
}