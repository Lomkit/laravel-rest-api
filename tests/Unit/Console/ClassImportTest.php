<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\Generation\ClassImport;
use Lomkit\Rest\Console\Generation\ClassReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ClassImportTest extends TestCase
{
    /** @param  list<string>  $takenNames */
    #[Test]
    #[DataProvider('imports')]
    public function it_imports_the_class_by_its_short_name_unless_the_name_is_taken(string $fqcn, array $takenNames, string $name, string $statement): void
    {
        $import = ClassImport::of($fqcn, $takenNames, 'Model');

        $this->assertSame($name, $import->name());
        $this->assertSame($statement, $import->statement());
    }

    /** @return array<string, array{string, list<string>, string, string}> */
    public static function imports(): array
    {
        return [
            'free name' => ['App\Models\Post', ['Resource', 'ID'], 'Post', 'App\Models\Post'],
            'taken name' => ['App\Models\Resource', ['Resource', 'ID'], 'ResourceModel', 'App\Models\Resource as ResourceModel'],
            'taken name in another case' => ['App\Models\Resource', ['resource'], 'ResourceModel', 'App\Models\Resource as ResourceModel'],
            'taken alias' => ['App\Models\Resource', ['Resource', 'ResourceModel'], 'ResourceModelModel', 'App\Models\Resource as ResourceModelModel'],
        ];
    }

    #[Test]
    public function it_imports_a_class_reference(): void
    {
        $import = ClassReference::fromInput('Controller', 'App\\', 'App\Rest\Resources\\')->import(['Controller'], 'Resource');

        $this->assertSame('ControllerResource', $import->name());
        $this->assertSame('App\Rest\Resources\Controller as ControllerResource', $import->statement());
    }
}
