<?php

namespace Lomkit\Rest\Tests\Feature\Translations;

use Illuminate\Filesystem\Filesystem;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.fallback_locale', 'en')]
class PublishedFrenchFileTest extends PackageTestCase
{
    use AssertsExactErrors;

    private string $langPath = '';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $this->langPath = sys_get_temp_dir().'/lomkit-rest-lang-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->ensureDirectoryExists($this->langPath.'/vendor/rest/fr');
        $files->put($this->langPath.'/vendor/rest/fr/validation.php', "<?php\n\nreturn ['unknown_key' => 'La clé :key n\\'est pas valide pour le champ :attribute.'];\n");
        $app->useLangPath($this->langPath);
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('fr');
    }

    #[\Override]
    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->langPath);

        parent::tearDown();
    }

    #[Test]
    public function it_answers_with_the_line_of_the_published_file(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Seating'], 'relations' => ['suppliers' => []]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations' => ['La clé suppliers n\'est pas valide pour le champ mutate.0.relations.']]);
    }

    #[Test]
    public function it_answers_in_english_a_line_that_the_published_file_does_not_hold(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['includes' => [['relation' => 'suppliers']]]]);

        $this->assertExactErrors($response, [
            'search.includes.0.relation' => ['The search.includes.0.relation field is not a valid or allowed relation for this resource.'],
        ]);
    }
}
