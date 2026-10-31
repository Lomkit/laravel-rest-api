<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Lomkit\Rest\Tests\Fixtures\Newsroom\Article;
use Lomkit\Rest\Tests\Fixtures\Newsroom\ArticleStatus;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Edition;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Publication;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GenerateResourceCommandTest extends ScaffoldingTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    #[Test]
    public function it_writes_only_the_key_without_the_option_in_non_interactive_mode(): void
    {
        $this->artisan('rest:resource', ['name' => 'QuietArticleResource', '--model' => Article::class, '--no-interaction' => true])
            ->doesntExpectOutputToContain(' relations')
            ->assertSuccessful();

        $this->assertStringContainsString(
            "use Lomkit\\Rest\\Fields\\ID;\nuse Lomkit\\Rest\\Http\\Requests\\RestRequest;\nuse Lomkit\\Rest\\Tests\\Fixtures\\Newsroom\\Article;\n\nclass QuietArticleResource extends Resource\n{\n    public static string \$model = Article::class;\n\n    #[\\Override]\n    public function fields(RestRequest \$request): array\n    {\n        return [\n            ID::make('id'),\n        ];\n    }\n\n    #[\\Override]\n    public function relations(RestRequest \$request): array\n    {\n        return [];\n    }\n",
            $this->resource('QuietArticleResource'),
        );
    }

    #[Test]
    public function it_declares_the_key_of_the_model_as_the_id_field(): void
    {
        $this->artisan('rest:resource', ['name' => 'KeyedEditionResource', '--model' => Edition::class, '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("        return [\n            ID::make('code'),\n        ];", $this->resource('KeyedEditionResource'));
    }

    #[Test]
    public function it_writes_only_the_key_without_opening_the_connection_of_the_model(): void
    {
        $this->writeModel('App\Models\Unconfigured', 'protected $connection = \'unconfigured\';');

        $this->artisan('rest:resource', ['name' => 'UnconfiguredResource', '--model' => 'Unconfigured', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("        return [\n            ID::make('id'),\n        ];", $this->resource('UnconfiguredResource'));
    }

    #[Test]
    public function it_writes_only_the_key_of_a_model_that_cannot_be_instantiated(): void
    {
        $this->artisan('rest:resource', ['name' => 'AbstractPublicationResource', '--model' => Publication::class, '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("        return [\n            ID::make('id'),\n        ];", $this->resource('AbstractPublicationResource'));
    }

    #[Test]
    #[DataProvider('unusableModels')]
    public function it_fails_before_writing_anything_when_the_model_cannot_be_read(string $name, ?string $model, string $message): void
    {
        $this->writeModel('App\Models\Unmigrated');

        $this->artisan('rest:resource', ['name' => $name, ...($model === null ? [] : ['--model' => $model]), '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain($message)
            ->assertFailed();

        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }

    /** @return array<string, array{string, string|null, string}> */
    public static function unusableModels(): array
    {
        return [
            'no model' => ['UnknownModelResource', null, 'No model was given or found for this resource. Pass --model to generate its fields.'],
            'a missing model' => ['MissingModelResource', 'Missing', 'The model [App\Models\Missing] does not exist.'],
            'a class that is not a model' => ['StatusResource', ArticleStatus::class, 'The class ['.ArticleStatus::class.'] is not an Eloquent model.'],
            'an abstract model' => ['PublicationResource', Publication::class, 'The model ['.Publication::class.'] cannot be instantiated. Pass a concrete model.'],
            'a missing table' => ['UnmigratedResource', 'Unmigrated', 'The table [unmigrateds] of the model [App\Models\Unmigrated] does not exist. Run the migrations, then generate the resource again.'],
        ];
    }

    #[Test]
    public function it_keeps_an_existing_resource_when_it_generates_again_without_force(): void
    {
        $this->artisan('rest:resource', ['name' => 'KeptArticleResource', '--model' => Article::class, '--generate' => true, '--no-interaction' => true])->assertSuccessful();
        file_put_contents(app_path('Rest/Resources/KeptArticleResource.php'), '<?php // edited');

        $this->artisan('rest:resource', ['name' => 'KeptArticleResource', '--model' => Article::class, '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Resource already exists.')
            ->doesntExpectOutputToContain('fields,')
            ->assertSuccessful();

        $this->assertSame('<?php // edited', $this->resource('KeptArticleResource'));
    }

    #[Test]
    public function it_generates_the_fields_again_with_force(): void
    {
        $this->artisan('rest:resource', ['name' => 'ForcedArticleResource', '--model' => Article::class, '--no-interaction' => true])->assertSuccessful();

        $this->artisan('rest:resource', ['name' => 'ForcedArticleResource', '--model' => Article::class, '--generate' => true, '--force' => true, '--no-interaction' => true])
            ->expectsOutputToContain('Resource [app/Rest/Resources/ForcedArticleResource.php] created successfully.')
            ->assertSuccessful();

        $this->assertStringContainsString("Text::make('title')", $this->resource('ForcedArticleResource'));
    }

    private function resource(string $name): string
    {
        return (string) file_get_contents(app_path("Rest/Resources/{$name}.php"));
    }
}
