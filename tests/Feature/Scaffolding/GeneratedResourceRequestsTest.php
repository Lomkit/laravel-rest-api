<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Article;
use Lomkit\Rest\Tests\Fixtures\Newsroom\ArticleStatus;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Edition;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GeneratedResourceRequestsTest extends ScaffoldingTestCase
{
    private const array ARTICLE = [
        'reference' => '0b4f2c7e-8d3a-4f6b-9e21-5c7d8a9b0e1f',
        'title' => 'Spring launch',
        'slug' => 'spring-launch',
        'body' => 'The spring range is out.',
        'word_count' => 450,
    ];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    #[Test]
    public function it_creates_and_searches_rows_through_a_generated_resource(): void
    {
        $this->serve('ServedArticle', Article::class, 'served-articles');

        $this->mutate('served-articles', [...self::ARTICLE, 'editor_token' => 'secret'])->assertOk();
        $response = $this->postJson('/served-articles/search', ['search' => ['selects' => [['field' => 'title'], ['field' => 'status'], ['field' => 'locale']]]]);

        $response->assertOk()->assertJsonPath('data', [['title' => 'Spring launch', 'locale' => 'en', 'status' => 'draft']]);
        $this->assertSame('secret', Article::query()->sole()->editor_token);
    }

    #[Test]
    public function it_never_returns_a_hidden_column_of_the_model(): void
    {
        $this->serve('HiddenArticle', Article::class, 'hidden-articles');
        Article::query()->create([...self::ARTICLE, 'editor_token' => 'secret']);

        $response = $this->postJson('/hidden-articles/search');

        $response->assertOk()
            ->assertJsonMissingPath('data.0.editor_token')
            ->assertJsonMissingPath('data.0.internal_token');
        $this->assertSame('Spring launch', $response->json('data.0.title'));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('refusedWrites')]
    public function it_refuses_the_writes_that_the_generated_fields_do_not_allow(string $name, array $attributes, array $errors): void
    {
        Article::query()->create([...self::ARTICLE, 'reference' => '7d1e3a5c-2b4f-4c6d-8e9f-0a1b2c3d4e5f', 'slug' => 'taken']);
        $this->serve($name, Article::class, 'refused-articles');

        $response = $this->mutate('refused-articles', $attributes);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame(1, Article::query()->count());
    }

    /** @return array<string, array{string, array<string, mixed>, array<string, list<string>>}> */
    public static function refusedWrites(): array
    {
        return [
            'a readonly timestamp' => ['ReadonlyArticle', [...self::ARTICLE, 'created_at' => '2026-01-01 09:00:00'], [
                'mutate.0.attributes' => ['The created_at attribute is not writable on create.'],
            ]],
            'a generated column' => ['GeneratedColumnArticle', [...self::ARTICLE, 'reading_minutes' => 3], [
                'mutate.0.attributes' => ['The reading_minutes attribute is not writable on create.'],
            ]],
            'a missing required column' => ['RequiredArticle', array_diff_key(self::ARTICLE, ['title' => true]), [
                'mutate.0.attributes.title' => ['The mutate.0.attributes.title field is required.'],
            ]],
            'an invalid enum value' => ['EnumArticle', [...self::ARTICLE, 'status' => 'archived'], [
                'mutate.0.attributes.status' => ['The selected mutate.0.attributes.status is invalid.'],
            ]],
            'a duplicate unique value' => ['UniqueArticle', [...self::ARTICLE, 'slug' => 'taken'], [
                'mutate.0.attributes.slug' => ['The mutate.0.attributes.slug has already been taken.'],
            ]],
            'a column left out' => ['LeftOutArticle', [...self::ARTICLE, 'internal_token' => 'secret'], [
                'mutate.0.attributes' => ['The internal_token attribute is not writable on create.'],
            ]],
        ];
    }

    #[Test]
    public function it_stores_an_enum_value_through_a_generated_resource(): void
    {
        $this->serve('PublishedArticle', Article::class, 'published-articles');

        $this->mutate('published-articles', [...self::ARTICLE, 'status' => 'published'])->assertOk();

        $this->assertSame(ArticleStatus::Published, Article::query()->sole()->status);
    }

    #[Test]
    public function it_writes_and_returns_a_json_column_that_the_model_does_not_cast(): void
    {
        $this->serve('MetadataArticle', Article::class, 'metadata-articles');
        $metadata = DB::connection()->getDriverName() === 'sqlite' ? '{"pages":4}' : ['pages' => 4];

        $this->mutate('metadata-articles', [...self::ARTICLE, 'metadata' => $metadata])->assertOk();
        $response = $this->postJson('/metadata-articles/search', ['search' => ['selects' => [['field' => 'metadata']]]]);

        $response->assertOk()->assertJsonPath('data', [['metadata' => $metadata]]);
        $this->assertSame(['pages' => 4], json_decode((string) Article::query()->sole()->metadata, true));
    }

    #[Test]
    public function it_writes_and_returns_the_columns_of_a_fluent_and_a_vector_cast(): void
    {
        $this->serve('CastArticle', Article::class, 'cast-articles');

        $this->mutate('cast-articles', [...self::ARTICLE, 'layout' => ['columns' => 2], 'embedding' => [0.5, 1.5]])->assertOk();
        $response = $this->postJson('/cast-articles/search', ['search' => ['selects' => [['field' => 'layout'], ['field' => 'embedding']]]]);

        $response->assertOk()->assertJsonPath('data', [['layout' => ['columns' => 2], 'embedding' => [0.5, 1.5]]]);
        $this->assertSame(['columns' => 2], json_decode((string) DB::table('articles')->value('layout'), true));
        $this->assertSame([0.5, 1.5], json_decode((string) DB::table('articles')->value('embedding'), true));
    }

    #[Test]
    public function it_takes_a_string_key_from_the_client_on_create(): void
    {
        $this->serve('KeyedEdition', Edition::class, 'keyed-editions');

        $this->mutate('keyed-editions', ['name' => 'Morning'])
            ->assertUnprocessable()
            ->assertJsonPath('errors', ['mutate.0.attributes.code' => ['The mutate.0.attributes.code field is required.']]);
        $this->mutate('keyed-editions', ['code' => 'am', 'name' => 'Morning'])->assertOk();

        $this->assertDatabaseHas('editions', ['code' => 'am', 'name' => 'Morning']);
        $this->assertSame(1, Edition::query()->count());
    }

    private function serve(string $name, string $model, string $uri): void
    {
        $this->artisan('rest:resource', ['name' => "{$name}Resource", '--model' => $model, '--generate' => true, '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:controller', ['name' => "{$name}Controller", '--resource' => "{$name}Resource", '--no-interaction' => true])->assertSuccessful();
        $this->loadGeneratedClass("App\\Rest\\Controllers\\{$name}Controller");

        Rest::resource($uri, "App\\Rest\\Controllers\\{$name}Controller")->register();
    }

    /** @param  array<string, mixed>  $attributes */
    private function mutate(string $uri, array $attributes): TestResponse
    {
        return $this->postJson("/{$uri}/mutate", ['mutate' => [['operation' => 'create', 'attributes' => $attributes]]]);
    }
}
