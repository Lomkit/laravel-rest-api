<?php

namespace Lomkit\Rest\Tests\Feature\Controllers;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Feature\TestCase;
use Lomkit\Rest\Tests\Support\Database\Factories\SoftDeletedModelFactory;
use Lomkit\Rest\Tests\Support\Models\SoftDeletedModel;
use Lomkit\Rest\Tests\Support\Policies\GreenPolicy;

class OrPerimeterPrecedenceTest extends TestCase
{
    /**
     * The statements captured for the request under test.
     *
     * @var array
     */
    protected array $statements = [];

    protected function setUp(): void
    {
        parent::setUp();

        Gate::policy(SoftDeletedModel::class, GreenPolicy::class);

        DB::listen(function ($query) {
            $this->statements[] = $query->sql;
        });
    }

    public function test_destroying_only_reaches_the_named_models_when_the_perimeter_holds_an_or(): void
    {
        $named = SoftDeletedModelFactory::new()->createOne(['string' => 'shared']);
        $notNamed = SoftDeletedModelFactory::new()->count(3)->create(['string' => 'owned']);

        $response = $this->delete(
            '/api/or-perimeter-soft-deleted-models',
            [
                'resources' => [$named->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        $this->assertCount(
            1,
            $response->json('data'),
            'The endpoint acted on models the caller never named, fetched by: '.$this->perimeterStatement()
        );
        $this->assertSoftDeleted($named);

        foreach ($notNamed as $model) {
            $this->assertNotSoftDeleted($model);
        }

        $this->assertPerimeterIsGrouped();
    }

    public function test_restoring_only_reaches_the_named_models_when_the_perimeter_holds_an_or(): void
    {
        $named = SoftDeletedModelFactory::new()->trashed()->createOne(['string' => 'shared']);
        $notNamed = SoftDeletedModelFactory::new()->count(3)->trashed()->create(['string' => 'owned']);

        $response = $this->post(
            '/api/or-perimeter-soft-deleted-models/restore',
            [
                'resources' => [$named->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        $this->assertCount(
            1,
            $response->json('data'),
            'The endpoint acted on models the caller never named, fetched by: '.$this->perimeterStatement()
        );
        $this->assertNotSoftDeleted($named);

        foreach ($notNamed as $model) {
            $this->assertSoftDeleted($model);
        }

        $this->assertPerimeterIsGrouped();
    }

    public function test_force_deleting_only_reaches_the_named_models_when_the_perimeter_holds_an_or(): void
    {
        $named = SoftDeletedModelFactory::new()->trashed()->createOne(['string' => 'shared']);
        $notNamed = SoftDeletedModelFactory::new()->count(3)->trashed()->create(['string' => 'owned']);

        $response = $this->delete(
            '/api/or-perimeter-soft-deleted-models/force',
            [
                'resources' => [$named->getKey()],
            ],
            ['Accept' => 'application/json']
        );

        $response->assertStatus(200);

        $this->assertCount(
            1,
            $response->json('data'),
            'The endpoint acted on models the caller never named, fetched by: '.$this->perimeterStatement()
        );
        $this->assertDatabaseMissing('soft_deleted_models', $named->only('id'));

        foreach ($notNamed as $model) {
            $this->assertDatabaseHas('soft_deleted_models', $model->only('id'));
        }

        $this->assertPerimeterIsGrouped();
    }

    /**
     * Assert the perimeter reached the database as one group, so that the id allow-list
     * appended after it binds against the whole perimeter and not just its last branch.
     *
     * @return void
     */
    protected function assertPerimeterIsGrouped(): void
    {
        $statement = $this->perimeterStatement();

        $this->assertStringContainsString(
            '(string = ? or string = ?) and',
            $statement,
            'The perimeter was applied un-grouped, so the id allow-list only constrains its last branch: '.$statement
        );
    }

    /**
     * Find the select that fetched the models to act on, with the identifier quoting of the
     * current driver removed so the assertion holds on MySQL, PostgreSQL and SQLite alike.
     *
     * @return string
     */
    protected function perimeterStatement(): string
    {
        foreach ($this->statements as $statement) {
            $statement = str_replace(['`', '"', '[', ']'], '', $statement);

            if (str_starts_with($statement, 'select') && str_contains($statement, 'string = ?')) {
                return $statement;
            }
        }

        $this->fail('No select carrying the perimeter was captured, out of: '.implode(' | ', $this->statements));
    }
}
