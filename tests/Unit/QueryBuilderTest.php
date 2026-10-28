<?php

namespace Lomkit\Rest\Tests\Unit;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\User;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\TestCase;
use Mockery;

class QueryBuilderTest extends TestCase
{
    public function test_building_query_for_empty_params()
    {
        Auth::setUser(Mockery::mock(User::class));
        Gate::policy(Model::class, GreenPolicy::class);

        $queryBuilderMock = Mockery::mock(Builder::class, [new ModelResource])->makePartial();

        $queryBuilderMock->shouldReceive('applySorts')->with([])->never();
        $queryBuilderMock->shouldReceive('applyScopes')->with([])->never();
        $queryBuilderMock->shouldReceive('applyFilters')->with([])->never();
        $queryBuilderMock->shouldReceive('applyIncludes')->with([])->never();

        $queryBuilderMock
            ->search();
    }

    public function test_building_query_for_sorts()
    {
        Auth::setUser(Mockery::mock(User::class));
        Gate::policy(Model::class, GreenPolicy::class);

        $queryBuilderMock = Mockery::mock(Builder::class, [new ModelResource])->makePartial();

        $queryBuilderMock->shouldReceive('applySorts')->with(
            [
                ['field' => 'test'],
            ]
        )->once();
        $queryBuilderMock->shouldReceive('applyScopes')->with([])->never();
        $queryBuilderMock->shouldReceive('applyFilters')->with([])->never();
        $queryBuilderMock->shouldReceive('applyIncludes')->with([])->never();

        $queryBuilderMock
            ->search([
                'sorts' => [
                    ['field' => 'test'],
                ],
            ]);
    }
}
