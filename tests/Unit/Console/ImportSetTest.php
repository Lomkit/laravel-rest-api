<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\Generation\ImportSet;
use Lomkit\Rest\Fields\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ImportSetTest extends TestCase
{
    #[Test]
    #[DataProvider('models')]
    public function it_imports_a_model_by_its_short_name_unless_the_resource_already_uses_it(string $resource, string $model, string $name, string $statement): void
    {
        $imports = ImportSet::forResource($resource, 'App\Rest\Resources\Resource')->with($model, 'Model');

        $this->assertSame($name, $imports->name($model));
        $this->assertSame($statement, $imports->statement($model));
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function models(): array
    {
        return [
            'free name' => ['App\Rest\Resources\PostResource', 'App\Models\Post', 'Post', 'App\Models\Post'],
            'the base resource' => ['App\Rest\Resources\LibraryResource', 'App\Models\Resource', 'ResourceModel', 'App\Models\Resource as ResourceModel'],
            'the base resource in another case' => ['App\Rest\Resources\LibraryResource', 'App\Models\RESOURCE', 'RESOURCEModel', 'App\Models\RESOURCE as RESOURCEModel'],
            'the resource itself' => ['App\Rest\Resources\Shelf', 'App\Models\Shelf', 'ShelfModel', 'App\Models\Shelf as ShelfModel'],
            'a taken alias' => ['App\Rest\Resources\ResourceModel', 'App\Models\Resource', 'ResourceModelModel', 'App\Models\Resource as ResourceModelModel'],
            'a field' => ['App\Rest\Resources\BadgeResource', 'App\Models\Text', 'TextModel', 'App\Models\Text as TextModel'],
            'a relation' => ['App\Rest\Resources\GraphResource', 'App\Models\HasMany', 'HasManyModel', 'App\Models\HasMany as HasManyModel'],
            'the validation rule' => ['App\Rest\Resources\PolicyResource', 'App\Models\Rule', 'RuleModel', 'App\Models\Rule as RuleModel'],
            'the request' => ['App\Rest\Resources\LogResource', 'App\Models\RestRequest', 'RestRequestModel', 'App\Models\RestRequest as RestRequestModel'],
        ];
    }

    #[Test]
    public function it_keeps_the_short_name_of_the_package_classes(): void
    {
        $imports = ImportSet::forResource('App\Rest\Resources\PostResource', 'App\Rest\Resources\Resource')
            ->with('App\Models\Text', 'Model')
            ->with(Text::class, 'Field');

        $this->assertSame('Text', $imports->name(Text::class));
        $this->assertSame(Text::class, $imports->statement(Text::class));
    }

    #[Test]
    public function it_aliases_a_second_class_with_the_same_short_name(): void
    {
        $imports = ImportSet::forResource('App\Rest\Resources\PostResource', 'App\Rest\Resources\Resource')
            ->with('App\Models\Post', 'Model')
            ->with('App\Archive\Post', 'Model');

        $this->assertSame('Post', $imports->name('App\Models\Post'));
        $this->assertSame('App\Archive\Post as PostModel', $imports->statement('App\Archive\Post'));
    }

    #[Test]
    public function it_names_a_class_once_however_often_it_is_added(): void
    {
        $imports = ImportSet::forResource('App\Rest\Resources\PostResource', 'App\Rest\Resources\Resource')
            ->with('App\Models\Post', 'Model')
            ->with('App\Models\Post', 'Model');

        $this->assertSame('Post', $imports->name('App\Models\Post'));
    }

    #[Test]
    public function it_needs_no_statement_for_a_class_of_the_same_namespace(): void
    {
        $imports = ImportSet::forResource('App\Rest\Resources\PostResource', 'App\Rest\Resources\Resource')
            ->with('App\Rest\Resources\TagResource', 'Resource');

        $this->assertSame('TagResource', $imports->name('App\Rest\Resources\TagResource'));
        $this->assertNull($imports->statement('App\Rest\Resources\TagResource'));
    }

    #[Test]
    #[DataProvider('resources')]
    public function it_imports_a_resource_by_its_short_name_unless_the_controller_already_uses_it(string $controller, string $resource, string $name, string $statement): void
    {
        $imports = ImportSet::forController($controller, 'App\Rest\Controllers\Controller')->with($resource, 'Resource');

        $this->assertSame($name, $imports->name($resource));
        $this->assertSame($statement, $imports->statement($resource));
    }

    /** @return array<string, array{string, string, string, string}> */
    public static function resources(): array
    {
        return [
            'free name' => ['App\Rest\Controllers\PostsController', 'App\Rest\Resources\PostResource', 'PostResource', 'App\Rest\Resources\PostResource'],
            'the base controller' => ['App\Rest\Controllers\PostsController', 'App\Rest\Resources\Controller', 'ControllerResource', 'App\Rest\Resources\Controller as ControllerResource'],
            'the controller itself' => ['App\Rest\Controllers\Report', 'App\Rest\Resources\Report', 'ReportResource', 'App\Rest\Resources\Report as ReportResource'],
            'a field, free for a controller' => ['App\Rest\Controllers\TextsController', 'App\Rest\Resources\Text', 'Text', 'App\Rest\Resources\Text'],
        ];
    }
}
