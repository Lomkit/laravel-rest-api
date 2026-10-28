<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Visibility;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class EnumBindingTest extends TestCase
{
    #[Test]
    #[DataProvider('castsThatAreNotBackedEnums')]
    public function it_ignores_a_cast_that_is_not_a_backed_enum(string $cast): void
    {
        $field = Enum::make('visibility');
        $field->bind(new class(['visibility' => $cast]) extends Model
        {
            /** @param array<string, string> $declaredCasts */
            public function __construct(private readonly array $declaredCasts = [])
            {
                parent::__construct();
            }

            /** @return array<string, string> */
            protected function casts(): array
            {
                return $this->declaredCasts;
            }
        });

        $this->expectExceptionObject(InvalidFieldException::missingEnumClass('visibility'));

        $field->baseRules();
    }

    /** @return array<string, array{string}> */
    public static function castsThatAreNotBackedEnums(): array
    {
        return [
            'cast class' => [AsArrayObject::class],
            'pure enum' => [Visibility::class],
        ];
    }
}
