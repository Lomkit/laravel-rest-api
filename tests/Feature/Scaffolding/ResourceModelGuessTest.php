<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Domain\OutsideGuessArticleResource;
use App\Rest\Resources\Admin\NestedGuessArticleResource;
use App\Rest\Resources\GuessedArticleResource;
use App\Rest\Resources\OrphanResource;
use App\Rest\Resources\QualifiedGuessArticleResource;
use App\Rest\Resources\RootGuessArticleResource;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResourceModelGuessTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_guesses_the_model_from_the_resource_name_when_the_model_exists(): void
    {
        $this->writeModel('App\Models\GuessedArticle');

        $this->artisan('rest:resource', ['name' => 'GuessedArticleResource', '--no-interaction' => true])
            ->doesntExpectOutputToContain('No model was given or found')
            ->assertSuccessful();

        $this->loadGeneratedClass(GuessedArticleResource::class);

        $this->assertSame('App\Models\GuessedArticle', GuessedArticleResource::modelClass());
    }

    #[Test]
    public function it_guesses_a_nested_model_from_a_nested_resource_name(): void
    {
        $this->writeModel('App\Models\Admin\NestedGuessArticle');

        $this->artisan('rest:resource', ['name' => 'Admin/NestedGuessArticleResource', '--no-interaction' => true])
            ->doesntExpectOutputToContain('No model was given or found')
            ->assertSuccessful();

        $this->loadGeneratedClass(NestedGuessArticleResource::class);

        $this->assertSame('App\Models\Admin\NestedGuessArticle', NestedGuessArticleResource::modelClass());
    }

    #[Test]
    public function it_guesses_the_model_from_a_fully_qualified_resource_name(): void
    {
        $this->writeModel('App\Models\QualifiedGuessArticle');

        $this->artisan('rest:resource', ['name' => 'App\Rest\Resources\QualifiedGuessArticleResource', '--no-interaction' => true])
            ->doesntExpectOutputToContain('No model was given or found')
            ->assertSuccessful();

        $this->loadGeneratedClass(QualifiedGuessArticleResource::class);

        $this->assertSame('App\Models\QualifiedGuessArticle', QualifiedGuessArticleResource::modelClass());
    }

    #[Test]
    public function it_guesses_the_model_from_the_class_name_of_a_resource_outside_the_resources_namespace(): void
    {
        $this->writeModel('App\Models\OutsideGuessArticle');

        $this->artisan('rest:resource', ['name' => 'App\Domain\OutsideGuessArticleResource', '--no-interaction' => true])
            ->doesntExpectOutputToContain('No model was given or found')
            ->assertSuccessful();

        $this->loadGeneratedClass(OutsideGuessArticleResource::class);

        $this->assertSame('App\Models\OutsideGuessArticle', OutsideGuessArticleResource::modelClass());
    }

    #[Test]
    public function it_guesses_a_model_of_the_root_namespace_when_the_application_has_no_models_directory(): void
    {
        rmdir(app_path('Models'));
        $this->writeModel('App\RootGuessArticle');

        $this->artisan('rest:resource', ['name' => 'RootGuessArticleResource', '--no-interaction' => true])
            ->doesntExpectOutputToContain('No model was given or found')
            ->assertSuccessful();

        $this->loadGeneratedClass(RootGuessArticleResource::class);

        $this->assertSame('App\RootGuessArticle', RootGuessArticleResource::modelClass());
    }

    #[Test]
    public function it_leaves_the_model_out_and_warns_when_no_model_is_given_or_found(): void
    {
        $this->artisan('rest:resource', ['name' => 'OrphanResource', '--no-interaction' => true])
            ->expectsOutputToContain('No model was given or found for this resource. Pass --model to set it.')
            ->assertSuccessful();

        $this->loadGeneratedClass(OrphanResource::class);
        $this->assertStringContainsString(
            "namespace App\\Rest\\Resources;\n\nuse Lomkit\\Rest\\Fields\\ID;\nuse Lomkit\\Rest\\Http\\Requests\\RestRequest;\n\nclass OrphanResource extends Resource\n{\n    #[\\Override]\n    public function fields(",
            file_get_contents(app_path('Rest/Resources/OrphanResource.php')),
        );

        $this->expectExceptionObject(InvalidResourceException::missingModel(OrphanResource::class));

        OrphanResource::modelClass();
    }
}
