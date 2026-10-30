<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\Generation\ClassReference;
use Lomkit\Rest\Exceptions\InvalidClassReferenceException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClassReferenceTest extends TestCase
{
    #[Test]
    #[DataProvider('inputs')]
    public function it_qualifies_the_input_unless_it_is_already_qualified(string $input, string $fqcn): void
    {
        $this->assertSame($fqcn, ClassReference::fromInput($input, 'App\\', 'App\Models\\')->fqcn());
    }

    /** @return array<string, array{string, string}> */
    public static function inputs(): array
    {
        return [
            'short name' => ['Post', 'App\Models\Post'],
            'nested name with slashes' => ['Admin/Post', 'App\Models\Admin\Post'],
            'nested name with backslashes' => ['Admin\Post', 'App\Models\Admin\Post'],
            'leading backslash' => ['\Post', 'App\Models\Post'],
            'leading slash' => ['/Admin/Post', 'App\Models\Admin\Post'],
            'app namespace' => ['App\Domain\Post', 'App\Domain\Post'],
            'loadable class outside the app namespace' => ['\\'.Product::class, Product::class],
            'unknown class outside the app namespace' => ['Vendor\Package\Post', 'App\Models\Vendor\Package\Post'],
            'global class name' => ['Exception', 'App\Models\Exception'],
            'underscores and digits' => ['_Post_2', 'App\Models\_Post_2'],
        ];
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_rejects_an_input_that_is_not_a_valid_class_name(string $input): void
    {
        $this->expectExceptionObject(InvalidClassReferenceException::invalidName($input));

        ClassReference::fromInput($input, 'App\\', 'App\Models\\');
    }

    /** @return array<string, array{string}> */
    public static function invalidInputs(): array
    {
        return [
            'empty' => [''],
            'dash' => ['Post-1'],
            'space' => ['Blog Post'],
            'leading digit' => ['1Post'],
            'nested leading digit' => ['Admin/1Post'],
            'trailing slash' => ['Post/'],
            'trailing backslash' => ['Post\\'],
            'double slash' => ['Admin//Post'],
            'separators only' => ['\\'],
        ];
    }

    #[Test]
    public function it_tells_whether_the_class_exists(): void
    {
        $this->assertTrue(ClassReference::fromInput(Product::class, 'App\\', 'App\Models\\')->exists());
        $this->assertFalse(ClassReference::fromInput('Post', 'App\\', 'App\Models\\')->exists());
    }

    #[Test]
    public function it_guesses_the_model_of_a_resource_that_exists(): void
    {
        $this->assertSame(Product::class, ClassReference::guessModel('ProductResource', 'Lomkit\Rest\Tests\Fixtures\Catalog\\')?->fqcn());
        $this->assertSame(Product::class, ClassReference::guessModel('Catalog\ProductResource', 'Lomkit\Rest\Tests\Fixtures\\')?->fqcn());
        $this->assertSame(Product::class, ClassReference::guessModel('Product', 'Lomkit\Rest\Tests\Fixtures\Catalog\\')?->fqcn());
    }

    #[Test]
    public function it_guesses_the_resource_of_a_controller_that_exists(): void
    {
        $this->assertSame(ProductResource::class, ClassReference::guessResource('ProductsController', 'Lomkit\Rest\Tests\Fixtures\Catalog\\')?->fqcn());
        $this->assertSame(ProductResource::class, ClassReference::guessResource('Catalog\ProductController', 'Lomkit\Rest\Tests\Fixtures\\')?->fqcn());
    }

    #[Test]
    public function it_guesses_nothing_when_the_guessed_class_does_not_exist(): void
    {
        $this->assertNull(ClassReference::guessModel('ArticleResource', 'Lomkit\Rest\Tests\Fixtures\Catalog\\'));
        $this->assertNull(ClassReference::guessResource('ArticlesController', 'Lomkit\Rest\Tests\Fixtures\Catalog\\'));
    }

    #[Test]
    public function it_guesses_nothing_from_a_name_that_is_only_the_suffix(): void
    {
        $this->assertTrue(class_exists(Resource::class));
        $this->assertNull(ClassReference::guessResource('Controller', 'Lomkit\Rest\Http\\'));
        $this->assertNull(ClassReference::guessResource('Http\Controller', 'Lomkit\Rest\\'));
        $this->assertNull(ClassReference::guessModel('Resource', 'Lomkit\Rest\Tests\Fixtures\Catalog\\'));
    }
}
