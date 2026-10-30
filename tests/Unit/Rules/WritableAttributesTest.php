<?php

namespace Lomkit\Rest\Tests\Unit\Rules;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Rules\Mutate\WritableAttributes;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WritableAttributesTest extends TestCase
{
    /** @param list<string> $expected */
    #[Test]
    #[DataProvider('attributes')]
    public function it_refuses_each_key_that_is_not_writable_on_the_operation(WriteOperation $operation, mixed $value, array $expected): void
    {
        $fields = FieldSet::of([
            ID::make('id'),
            Text::make('name'),
            Text::make('slug')->updatable(false),
            Text::make('initials')->virtual(),
            Text::make('full_name')->virtual()->fillUsing(function (Model $model, mixed $value): void {}),
        ]);
        $failures = [];

        (new WritableAttributes($fields, $operation))->validate('mutate.0.attributes', $value, function (string $message) use (&$failures): void {
            $failures[] = $message;
        });

        $this->assertSame($expected, $failures);
    }

    /** @return array<string, array{WriteOperation, mixed, list<string>}> */
    public static function attributes(): array
    {
        return [
            'writable keys on create' => [WriteOperation::Create, ['name' => 'PHP', 'slug' => 'php', 'full_name' => 'PHP'], []],
            'writable keys on update' => [WriteOperation::Update, ['name' => 'PHP', 'full_name' => 'PHP'], []],
            'no key' => [WriteOperation::Create, [], []],
            'the key, a virtual field and an undeclared key on create' => [WriteOperation::Create, ['id' => 5, 'name' => 'PHP', 'initials' => 'P', 'stock' => 3], [
                'The id attribute is not writable on create.',
                'The initials attribute is not writable on create.',
                'The stock attribute is not writable on create.',
            ]],
            'a field that is not updatable on update' => [WriteOperation::Update, ['name' => 'PHP', 'slug' => 'php'], [
                'The slug attribute is not writable on update.',
            ]],
            'a list' => [WriteOperation::Create, ['PHP'], ['The 0 attribute is not writable on create.']],
            'a value that is not an array' => [WriteOperation::Create, 'PHP', []],
        ];
    }
}
