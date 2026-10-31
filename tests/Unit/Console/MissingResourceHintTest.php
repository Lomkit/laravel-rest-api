<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\Generation\MissingResourceHint;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MissingResourceHintTest extends TestCase
{
    #[Test]
    #[DataProvider('hints')]
    public function it_tells_how_to_give_the_related_model_a_resource(MissingResourceHint $hint, string $model, string $option, string $expected): void
    {
        $this->assertSame($expected, $hint->for($model, $option));
    }

    /** @return array<string, array{MissingResourceHint, string, string, string}> */
    public static function hints(): array
    {
        return [
            'generate the resource' => [MissingResourceHint::GenerateTheResource, 'App\Models\User', 'User', 'Generate one first with php artisan rest:resource UserResource --model=User --generate, then run this command again with --force.'],
            'expose the model' => [MissingResourceHint::ExposeTheModel, 'App\Models\User', 'User', 'Rerun with --model=User to expose users.'],
            'expose a model named with several words' => [MissingResourceHint::ExposeTheModel, 'App\Models\Admin\BlogPost', 'Admin/BlogPost', 'Rerun with --model=Admin/BlogPost to expose blog posts.'],
        ];
    }
}
