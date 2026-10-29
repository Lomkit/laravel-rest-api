<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class OneOfTest extends PackageTestCase
{
    #[Test]
    #[DataProvider('values')]
    public function it_gives_the_same_result_as_rule_in(mixed $value): void
    {
        $names = ['name', 'price', '42'];

        $this->assertSame(
            Validator::make(['field' => $value], ['field' => [Rule::in($names)]])->errors()->toArray(),
            Validator::make(['field' => $value], ['field' => [new OneOf($names)]])->errors()->toArray(),
        );
    }

    /** @return array<string, array{mixed}> */
    public static function values(): array
    {
        return [
            'a listed name' => ['price'],
            'a listed numeric name given as an integer' => [42],
            'an unknown name' => ['stock'],
            'a name in another case' => ['Price'],
            'a list holding a listed name' => [['price']],
            'null' => [null],
            'false' => [false],
            'an empty string' => [''],
        ];
    }

    #[Test]
    public function it_reports_the_in_message(): void
    {
        $validator = Validator::make(['search' => ['field' => 'stock']], ['search.field' => [new OneOf(['name', 'price'])]]);

        $this->assertSame(['search.field' => ['The selected search.field is invalid.']], $validator->errors()->toArray());
    }

    #[Test]
    public function it_uses_the_custom_attribute_name(): void
    {
        $validator = Validator::make(['field' => 'stock'], ['field' => [new OneOf(['name'])]], [], ['field' => 'sort field']);

        $this->assertSame(['field' => ['The selected sort field is invalid.']], $validator->errors()->toArray());
    }

    #[Test]
    public function it_exposes_its_values(): void
    {
        $this->assertSame(['name', 'price', '42'], (new OneOf(['name', 'price', '42']))->values());
    }
}
