<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Contracts\QueryBuilder;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Query\ScoutBuilder;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\User;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;
use Lomkit\Rest\Tests\Support\Rest\Resources\SearchableModelResource;
use Mockery\MockInterface;

class LaravelScoutTest extends \Lomkit\Rest\Tests\TestCase
{
    public function test_building_scout_query()
    {
        Auth::setUser(Mockery::mock(User::class));
        Gate::policy(Model::class, GreenPolicy::class);

        $scoutQueryBuilderMock = Mockery::mock(ScoutBuilder::class, [new SearchableModelResource])->makePartial();

        $scoutQueryBuilderMock->shouldReceive('applyFilters')->with([])->never();
        $scoutQueryBuilderMock->shouldReceive('applySorts')->with([])->never();
        $scoutQueryBuilderMock->shouldReceive('applyInstructions')->with([])->never();

        $scoutQueryBuilderMock
            ->search([
                'text' => [
                    'value' => 'my specific search',
                ],
            ]);

        $this->assertEquals('my specific search', $scoutQueryBuilderMock->toBase()->query);
    }

    public function test_building_scout_with_filters()
    {
        Auth::setUser(Mockery::mock(User::class));
        Gate::policy(Model::class, GreenPolicy::class);

        $scoutQueryBuilderMock = Mockery::mock(ScoutBuilder::class, [new SearchableModelResource])->makePartial();

        $scoutQueryBuilderMock->shouldReceive('applyFilters')->with([['field' => 'test', 'value' => 1]])->once();
        $scoutQueryBuilderMock->shouldReceive('applySorts')->with([])->never();
        $scoutQueryBuilderMock->shouldReceive('applyInstructions')->with([])->never();

        $scoutQueryBuilderMock
            ->search([
                'text' => [
                    ['value' => 'test'],
                ],
                'filters' => [
                    ['field' => 'test', 'value' => 1]
                ]
            ]);
    }

    public function test_building_scout_with_sorts()
    {
        Auth::setUser(Mockery::mock(User::class));
        Gate::policy(Model::class, GreenPolicy::class);

        $scoutQueryBuilderMock = Mockery::mock(ScoutBuilder::class, [new SearchableModelResource])->makePartial();

        $scoutQueryBuilderMock->shouldReceive('applyFilters')->with([])->never();
        $scoutQueryBuilderMock->shouldReceive('applySorts')->with([['field' => 'id']])->once();
        $scoutQueryBuilderMock->shouldReceive('applyInstructions')->with([])->never();

        $scoutQueryBuilderMock
            ->search([
                'text' => [
                    ['value' => 'test'],
                ],
                'sorts' => [
                    ['field' => 'id'],
                ]
            ]);
    }

    public function test_building_scout_with_instructions()
    {
        Auth::setUser(Mockery::mock(User::class));
        Gate::policy(Model::class, GreenPolicy::class);

        $scoutQueryBuilderMock = Mockery::mock(ScoutBuilder::class, [new SearchableModelResource])->makePartial();

        $scoutQueryBuilderMock->shouldReceive('applyFilters')->with([])->never();
        $scoutQueryBuilderMock->shouldReceive('applySorts')->with([])->never();
        $scoutQueryBuilderMock->shouldReceive('applyInstructions')->with([['name' => 'my_instruction']])->once();

        $scoutQueryBuilderMock
            ->search([
                'text' => [
                    ['value' => 'test'],
                ],
                'instructions' => [
                    ['name' => 'my_instruction'],
                ],
            ]);

        ($scoutQueryBuilderMock->toBase()->queryCallback)(Model::query());
    }
}
