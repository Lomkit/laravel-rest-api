<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\Generation\ClassFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClassFileTest extends TestCase
{
    private const string CLASS_NAME = 'App\Rest\Resources\PostResource';

    #[Test]
    #[DataProvider('files')]
    public function it_tells_whether_a_file_declares_only_the_given_child_class(string $code, bool $declares): void
    {
        $path = tempnam(sys_get_temp_dir(), 'lomkit-class-file-');
        $this->assertIsString($path);
        file_put_contents($path, $code);

        try {
            $this->assertSame($declares, ClassFile::read($path)->declaresOnlyTheChildClass(self::CLASS_NAME));
        } finally {
            unlink($path);
        }
    }

    /** @return array<string, array{string, bool}> */
    public static function files(): array
    {
        $namespace = "<?php\n\nnamespace App\\Rest\\Resources;\n\n";

        return [
            'a child class' => [$namespace.'class PostResource extends Resource {}', true],
            'a final readonly child class' => [$namespace."#[Attribute]\nfinal readonly class PostResource extends Resource {}", true],
            'a child class that names other classes' => [$namespace."use Foo;\n\nclass PostResource extends Resource\n{\n    public string \$model = Post::class;\n\n    public function anonymous(): object\n    {\n        return new class {};\n    }\n}", true],
            'an abstract class' => [$namespace.'abstract class PostResource extends Resource {}', false],
            'an abstract readonly class' => [$namespace.'abstract readonly class PostResource extends Resource {}', false],
            'a class without a parent' => [$namespace.'class PostResource {}', false],
            'a class with another name' => [$namespace.'class TagResource extends Resource {}', false],
            'a class in another namespace' => ["<?php\n\nnamespace App\\Rest;\n\nclass PostResource extends Resource {}", false],
            'a class without a namespace' => ["<?php\n\nclass PostResource extends Resource {}", false],
            'a second class' => [$namespace."class PostResource extends Resource {}\n\nclass TagResource extends Resource {}", false],
            'an interface' => [$namespace.'interface PostResource extends Resource {}', false],
            'a trait' => [$namespace.'trait PostResource {}', false],
            'an enum' => [$namespace.'enum PostResource {}', false],
            'a script' => ["<?php\n\nreturn ['class' => PostResource::class];", false],
            'an empty file' => ['', false],
        ];
    }
}
