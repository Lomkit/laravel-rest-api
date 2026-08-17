<?php

namespace Controllers;

use Illuminate\Database\Eloquent\Model as EloquentModel;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\BelongsToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\HasManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\ModelFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphedByManyRelationFactory;
use Lomkit\Rest\Tests\Support\Database\Factories\MorphToManyRelationFactory;
use Lomkit\Rest\Tests\Support\Models\BelongsToManyRelation;
use Lomkit\Rest\Tests\Support\Models\HasManyRelation;
use Lomkit\Rest\Tests\Support\Models\Model;
use Lomkit\Rest\Tests\Support\Models\MorphedByManyRelation;
use Lomkit\Rest\Tests\Support\Models\MorphToManyRelation;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;
use Lomkit\Rest\Tests\Support\Policies\ViewPolicy;
use PHPUnit\Framework\Attributes\DataProvider;

class MutateNestedRelationAuthorizationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(Model::class, GreenPolicy::class);
    }

    public static function writableRelationProvider(): array
    {
        return [
            'belongsToMany'  => ['belongsToManyRelation', BelongsToManyRelation::class],
            'hasMany'        => ['hasManyRelation', HasManyRelation::class],
            'morphToMany'    => ['morphToManyRelation', MorphToManyRelation::class],
            'morphedByMany'  => ['morphedByManyRelation', MorphedByManyRelation::class],
        ];
    }

    protected function makeRelated(string $relation): EloquentModel
    {
        return match ($relation) {
            'belongsToManyRelation' => BelongsToManyRelationFactory::new()->createOne(['number' => 1]),
            'hasManyRelation'       => HasManyRelationFactory::new()->createOne(['number' => 1]),
            'morphToManyRelation'   => MorphToManyRelationFactory::new()->createOne(['number' => 1]),
            'morphedByManyRelation' => MorphedByManyRelationFactory::new()->createOne(['number' => 1]),
        };
    }

    protected function mutateNested(string $relation, array $nested): \Illuminate\Testing\TestResponse
    {
        $model = ModelFactory::new()->createOne();

        return $this->post(
            '/api/gated-models/mutate',
            [
                'mutate' => [
                    [
                        'operation'  => 'update',
                        'key'        => $model->getKey(),
                        'attributes' => [],
                        'relations'  => [$relation => [$nested]],
                    ],
                ],
            ],
            ['Accept' => 'application/json']
        );
    }

    #[DataProvider('writableRelationProvider')]
    public function test_sync_cannot_write_attributes_without_the_update_ability(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, ViewPolicy::class);
        $victim = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation'  => 'sync',
            'key'        => $victim->getKey(),
            'attributes' => ['number' => 5],
        ]);

        $response->assertStatus(403);
        $this->assertEquals(1, $victim->fresh()->number);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_toggle_cannot_write_attributes_without_the_update_ability(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, ViewPolicy::class);
        $victim = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation'  => 'toggle',
            'key'        => $victim->getKey(),
            'attributes' => ['number' => 5],
        ]);

        $response->assertStatus(403);
        $this->assertEquals(1, $victim->fresh()->number);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_update_is_the_control_and_is_equally_refused(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, ViewPolicy::class);
        $victim = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation'  => 'update',
            'key'        => $victim->getKey(),
            'attributes' => ['number' => 5],
        ]);

        $response->assertStatus(403);
        $this->assertEquals(1, $victim->fresh()->number);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_sync_applies_update_rules_to_nested_attributes(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, GreenPolicy::class);
        $victim = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation'  => 'sync',
            'key'        => $victim->getKey(),
            'attributes' => ['number' => 999999],
        ]);

        $response->assertStatus(422);
        $this->assertEquals(1, $victim->fresh()->number);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_toggle_applies_update_rules_to_nested_attributes(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, GreenPolicy::class);
        $victim = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation'  => 'toggle',
            'key'        => $victim->getKey(),
            'attributes' => ['number' => 999999],
        ]);

        $response->assertStatus(422);
        $this->assertEquals(1, $victim->fresh()->number);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_sync_still_writes_attributes_when_update_is_allowed(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, GreenPolicy::class);
        $target = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation'  => 'sync',
            'key'        => $target->getKey(),
            'attributes' => ['number' => 9],
        ]);

        $response->assertStatus(200);
        $this->assertEquals(9, $target->fresh()->number);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_sync_without_attributes_only_requires_the_view_ability(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, ViewPolicy::class);
        $target = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation' => 'sync',
            'key'       => $target->getKey(),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $target->fresh()->number);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_toggle_without_attributes_only_requires_the_view_ability(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, ViewPolicy::class);
        $target = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation' => 'toggle',
            'key'       => $target->getKey(),
        ]);

        $response->assertStatus(200);
        $this->assertEquals(1, $target->fresh()->number);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_attach_only_requires_the_view_ability(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, ViewPolicy::class);
        $target = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation' => 'attach',
            'key'       => $target->getKey(),
        ]);

        $response->assertStatus(200);
    }

    #[DataProvider('writableRelationProvider')]
    public function test_detach_only_requires_the_view_ability(string $relation, string $relatedModel): void
    {
        Gate::policy($relatedModel, ViewPolicy::class);
        $target = $this->makeRelated($relation);

        $response = $this->mutateNested($relation, [
            'operation' => 'detach',
            'key'       => $target->getKey(),
        ]);

        $response->assertStatus(200);
    }
}
