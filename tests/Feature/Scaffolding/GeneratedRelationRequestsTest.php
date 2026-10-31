<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Desk;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Story;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Topic;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GeneratedRelationRequestsTest extends ScaffoldingTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    #[Test]
    public function it_creates_a_row_with_the_required_belongs_to_relation(): void
    {
        $this->serveStories('Linked');
        $desk = Desk::query()->create(['name' => 'Politics']);

        $this->mutate('linked', ['headline' => 'Budget vote'], ['desk' => ['operation' => 'attach', 'key' => $desk->id]])->assertOk();

        $this->assertSame($desk->id, Story::query()->sole()->desk_id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('refusedStories')]
    public function it_refuses_a_story_that_the_generated_relations_do_not_allow(string $prefix, array $attributes, bool $withDesk, array $errors): void
    {
        $this->serveStories($prefix);
        $desk = Desk::query()->create(['name' => 'Politics']);
        $relations = $withDesk ? ['desk' => ['operation' => 'attach', 'key' => $desk->id]] : [];

        $response = $this->mutate(strtolower($prefix), [...$attributes, 'headline' => 'Budget vote'], $relations);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame(0, Story::query()->count());
    }

    /** @return array<string, array{string, array<string, mixed>, bool, array<string, list<string>>}> */
    public static function refusedStories(): array
    {
        return [
            'a missing required relation' => ['MissingDesk', [], false, [
                'mutate.0.relations.desk' => ['The mutate.0.relations.desk field is required.'],
            ]],
            'the key of a belongs to relation' => ['KeyedDesk', ['desk_id' => 1], true, [
                'mutate.0.attributes' => ['The desk_id attribute is not writable on create.'],
            ]],
        ];
    }

    #[Test]
    public function it_attaches_and_returns_the_generated_pivot_fields(): void
    {
        $this->serveStories('Pivoted');
        $desk = Desk::query()->create(['name' => 'Politics']);
        $topic = Topic::query()->create(['name' => 'Budget']);

        $this->mutate('pivoted', ['headline' => 'Budget vote'], [
            'desk' => ['operation' => 'attach', 'key' => $desk->id],
            'topics' => [['operation' => 'attach', 'key' => $topic->id, 'pivot' => ['position' => 2, 'note' => 'Lead', 'pinned' => true]]],
        ])->assertOk();
        $response = $this->postJson('/pivoted-stories/search', ['search' => ['includes' => [['relation' => 'topics']]]]);

        $response->assertOk();
        $pivot = $response->json('data.0.topics.0.pivot');
        $this->assertIsArray($pivot);
        $this->assertSame(['badge', 'created_at', 'details', 'note', 'pinned', 'position', 'updated_at'], $this->sortedKeys($pivot));
        $this->assertNotNull($pivot['created_at']);
        $this->assertSame([2, 'Lead'], [(int) $pivot['position'], $pivot['note']]);
        $this->assertDatabaseHas('story_topic', ['topic_id' => $topic->id, 'position' => 2, 'note' => 'Lead']);
    }

    #[Test]
    public function it_writes_and_returns_a_json_pivot_column_that_the_pivot_model_does_not_cast(): void
    {
        $this->serveStories('Detailed');
        $desk = Desk::query()->create(['name' => 'Politics']);
        $topic = Topic::query()->create(['name' => 'Budget']);
        $details = DB::connection()->getDriverName() === 'sqlite' ? '{"tier":1}' : ['tier' => 1];

        $this->mutate('detailed', ['headline' => 'Budget vote'], [
            'desk' => ['operation' => 'attach', 'key' => $desk->id],
            'topics' => [['operation' => 'attach', 'key' => $topic->id, 'pivot' => ['pinned' => true, 'details' => $details]]],
        ])->assertOk();
        $response = $this->postJson('/detailed-stories/search', ['search' => ['includes' => [['relation' => 'topics']]]]);

        $response->assertOk()->assertJsonPath('data.0.topics.0.pivot.details', $details);
        $this->assertSame(['tier' => 1], json_decode((string) DB::table('story_topic')->value('details'), true));
    }

    /**
     * @param  array<string, mixed>  $pivot
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('refusedPivots')]
    public function it_refuses_the_pivot_values_that_the_generated_pivot_fields_do_not_allow(string $prefix, array $pivot, array $errors): void
    {
        $this->serveStories($prefix);
        $desk = Desk::query()->create(['name' => 'Politics']);
        $topic = Topic::query()->create(['name' => 'Budget']);

        $response = $this->mutate(strtolower($prefix), ['headline' => 'Budget vote'], [
            'desk' => ['operation' => 'attach', 'key' => $desk->id],
            'topics' => [['operation' => 'attach', 'key' => $topic->id, 'pivot' => $pivot]],
        ]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame(0, Story::query()->count());
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function refusedPivots(): array
    {
        return [
            'a readonly pivot timestamp' => ['StampedPivot', ['pinned' => true, 'created_at' => '2026-01-01 09:00:00'], [
                'mutate.0.relations.topics.0.pivot' => ['The created_at attribute is not writable on create.'],
            ]],
            'a missing required pivot column' => ['UnpinnedPivot', ['position' => 1], [
                'mutate.0.relations.topics.0.pivot.pinned' => ['The mutate.0.relations.topics.0.pivot.pinned field is required.'],
            ]],
        ];
    }

    private function serveStories(string $prefix): void
    {
        $this->generate("{$prefix}DeskResource", Desk::class);
        $this->generate("{$prefix}TopicResource", Topic::class);
        $this->generate("{$prefix}StoryResource", Story::class);
        $this->artisan('rest:controller', ['name' => "{$prefix}StoriesController", '--resource' => "{$prefix}StoryResource", '--no-interaction' => true])->assertSuccessful();
        $this->loadGeneratedClass("App\\Rest\\Controllers\\{$prefix}StoriesController");

        Rest::resource(strtolower($prefix).'-stories', "App\\Rest\\Controllers\\{$prefix}StoriesController")->register();
    }

    private function generate(string $name, string $model): void
    {
        $this->artisan('rest:resource', ['name' => $name, '--model' => $model, '--generate' => true, '--no-interaction' => true])->assertSuccessful();
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, mixed>  $relations
     */
    private function mutate(string $prefix, array $attributes, array $relations): TestResponse
    {
        return $this->postJson("/{$prefix}-stories/mutate", ['mutate' => [[
            'operation' => 'create',
            'attributes' => $attributes,
            ...($relations === [] ? [] : ['relations' => $relations]),
        ]]]);
    }

    /**
     * @param  array<array-key, mixed>  $values
     * @return list<array-key>
     */
    private function sortedKeys(array $values): array
    {
        $keys = array_keys($values);
        sort($keys);

        return $keys;
    }
}
