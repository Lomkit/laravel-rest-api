<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Article;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Publication;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class GenerateResourcePromptTest extends ScaffoldingTestCase
{
    private const string GENERATE_ARTICLES = 'Generate the fields and relations from the articles table?';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    #[Test]
    public function it_generates_the_fields_when_the_question_is_confirmed(): void
    {
        $this->artisan('rest:resource', ['name' => 'ConfirmedArticleResource', '--model' => Article::class])
            ->expectsConfirmation(self::GENERATE_ARTICLES, 'yes')
            ->assertSuccessful();

        $this->assertStringContainsString("Text::make('title')", $this->resource('ConfirmedArticleResource'));
    }

    #[Test]
    public function it_writes_only_the_key_when_the_question_is_declined(): void
    {
        $this->artisan('rest:resource', ['name' => 'DeclinedArticleResource', '--model' => Article::class])
            ->expectsConfirmation(self::GENERATE_ARTICLES, 'no')
            ->doesntExpectOutputToContain(' relations')
            ->assertSuccessful();

        $this->assertStringContainsString("        return [\n            ID::make('id'),\n        ];", $this->resource('DeclinedArticleResource'));
        $this->assertStringNotContainsString("Text::make('title')", $this->resource('DeclinedArticleResource'));
    }

    #[Test]
    public function it_generates_the_fields_when_the_question_is_answered_with_its_default(): void
    {
        $input = new ArrayInput(['name' => 'DefaultArticleResource', '--model' => Article::class]);
        $input->setStream($this->answers("\n"));

        $exitCode = Artisan::all()['rest:resource']->run($input, $output = new BufferedOutput);

        $this->assertSame(0, $exitCode, $output->fetch());
        $this->assertStringContainsString("Text::make('title')", $this->resource('DefaultArticleResource'));
    }

    #[Test]
    public function it_asks_to_generate_after_the_name_and_the_model(): void
    {
        $this->artisan('rest:resource')
            ->expectsQuestion('What should the resource be named?', 'PromptedArticleResource')
            ->expectsChoice('What model should this resource rely on? (Optional)', Article::class, [], true)
            ->expectsConfirmation(self::GENERATE_ARTICLES, 'yes')
            ->assertSuccessful();

        $this->assertStringContainsString("Text::make('title')", $this->resource('PromptedArticleResource'));
    }

    #[Test]
    public function it_does_not_ask_to_generate_when_the_table_does_not_exist(): void
    {
        $this->writeModel('App\Models\Tableless');

        $this->artisan('rest:resource', ['name' => 'TablelessResource', '--model' => 'Tableless'])->assertSuccessful();

        $this->assertStringContainsString("ID::make('id'),\n        ];", $this->resource('TablelessResource'));
    }

    #[Test]
    public function it_does_not_ask_to_generate_for_a_model_that_cannot_be_instantiated(): void
    {
        $this->artisan('rest:resource', ['name' => 'PromptedPublicationResource', '--model' => Publication::class])->assertSuccessful();

        $this->assertStringContainsString("ID::make('id'),\n        ];", $this->resource('PromptedPublicationResource'));
    }

    #[Test]
    public function it_does_not_ask_to_generate_when_the_database_cannot_be_reached(): void
    {
        config(['database.connections.unreachable' => ['driver' => 'sqlite', 'database' => base_path('missing.sqlite')]]);
        $this->writeModel('App\Models\Unreachable', 'protected $connection = \'unreachable\';');

        $this->artisan('rest:resource', ['name' => 'UnreachableResource', '--model' => 'Unreachable'])->assertSuccessful();

        $this->assertStringContainsString("ID::make('id'),\n        ];", $this->resource('UnreachableResource'));
    }

    #[Test]
    #[DataProvider('generateOptions')]
    public function it_does_not_ask_to_generate_when_the_option_is_given(string $option): void
    {
        $this->artisan('rest:resource', ['name' => 'OptionArticleResource', '--model' => Article::class, $option => true])->assertSuccessful();

        $this->assertStringContainsString("Text::make('title')", $this->resource('OptionArticleResource'));
    }

    /** @return array<string, array{string}> */
    public static function generateOptions(): array
    {
        return [
            'long option' => ['--generate'],
            'short option' => ['-G'],
        ];
    }

    private function resource(string $name): string
    {
        return (string) file_get_contents(app_path("Rest/Resources/{$name}.php"));
    }

    /** @return resource */
    private function answers(string $answers)
    {
        $stream = fopen('php://memory', 'r+');
        $this->assertIsResource($stream);
        fwrite($stream, $answers);
        rewind($stream);

        return $stream;
    }
}
