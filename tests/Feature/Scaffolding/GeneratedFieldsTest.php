<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Article;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Attachment;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Edition;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Resource as ResourceModel;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Rule;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GeneratedFieldsTest extends ScaffoldingTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    #[Test]
    #[DataProvider('models')]
    public function it_generates_a_field_for_each_column_of_the_table(string $name, string $model): void
    {
        $this->artisan('rest:resource', ['name' => $name, '--model' => $model, '--generate' => true, '--no-interaction' => true])->assertSuccessful();

        $this->assertStringEqualsFile(
            __DIR__.'/Golden/'.DB::connection()->getDriverName()."/{$name}.golden",
            (string) file_get_contents(app_path("Rest/Resources/{$name}.php")),
        );

        $resource = "App\\Rest\\Resources\\{$name}";
        $this->loadGeneratedClass($resource);
        $this->assertInstanceOf(Resource::class, $instance = new $resource);
        $this->assertSame($model, $resource::modelClass());
        $this->assertCount(
            substr_count((string) file_get_contents(app_path("Rest/Resources/{$name}.php")), '::make('),
            FieldSet::of($instance->fields(new RestRequest)),
        );
    }

    /** @return array<string, array{string, class-string}> */
    public static function models(): array
    {
        return [
            'every column type' => ['ArticleResource', Article::class],
            'a string key' => ['EditionResource', Edition::class],
            'a uuid key' => ['AttachmentResource', Attachment::class],
            'a model named like the base resource' => ['AssetResource', ResourceModel::class],
            'a model named like the validation rule' => ['RuleResource', Rule::class],
        ];
    }
}
