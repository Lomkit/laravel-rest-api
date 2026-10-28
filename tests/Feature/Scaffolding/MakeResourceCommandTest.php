<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class MakeResourceCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_the_resource_class_in_the_app_directory(): void
    {
        $this->artisan('rest:resource', ['name' => 'ProductResource', '--model' => 'Product'])->assertSuccessful();

        $this->assertFileExists(app_path('Rest/Resources/ProductResource.php'));
        $this->assertStringContainsString(
            'public static $model = \App\Product::class;',
            file_get_contents(app_path('Rest/Resources/ProductResource.php')),
        );
    }
}
