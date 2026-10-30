<?php

namespace Lomkit\Rest\Tests\Unit\Scramble;

use Illuminate\Validation\Rules\Unique;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Fields\WriteOperation;
use Lomkit\Rest\Scramble\WritableFieldsType;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WritableFieldsTypeTest extends TestCase
{
    #[Test]
    public function it_documents_the_fields_writable_on_each_operation_with_their_rules(): void
    {
        $fields = FieldSet::of([
            ID::make('id'),
            Text::make('title')
                ->rules('max:60')
                ->creationRules('required'),
            Integer::make('views')->creatable(false),
            DateTime::make('created_at')->readonly(),
        ]);

        $type = WritableFieldsType::of($fields, ['create' => WriteOperation::Create, 'update' => WriteOperation::Update, 'detach' => null]);

        $this->assertSame([
            'type' => 'object',
            'description' => 'Writable on create: title. Writable on update: title, views. Writable on detach: none.',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'create: string, max:60, required | update: string, max:60'],
                'views' => ['type' => 'integer', 'description' => 'update: integer'],
            ],
        ], $type->toType()->toArray());
    }

    #[Test]
    public function it_documents_the_shared_rules_before_the_rules_of_each_operation(): void
    {
        $fields = FieldSet::of([Text::make('title'), Text::make('code')]);

        $type = WritableFieldsType::of(
            $fields,
            ['create' => WriteOperation::Create, 'update' => WriteOperation::Update],
            ['create' => ['title' => [new Unique('posts')], 'code' => 'integer|min:1'], 'update' => ['title' => 'min:3']],
            ['title' => 'string|max:60'],
        );

        $this->assertSame([
            'type' => 'object',
            'description' => 'Writable on create: title, code. Writable on update: title, code.',
            'properties' => [
                'title' => ['type' => 'string', 'description' => 'all: string, max:60 | create: string | update: string, min:3'],
                'code' => ['type' => 'integer', 'description' => 'create: string, integer, min:1 | update: string'],
            ],
        ], $type->toType()->toArray());
    }

    #[Test]
    public function it_documents_an_object_without_properties_when_no_field_is_writable(): void
    {
        $type = WritableFieldsType::of(FieldSet::of([ID::make('id')]), ['create' => WriteOperation::Create]);

        $this->assertSame(['type' => 'object', 'description' => 'Writable on create: none.'], $type->toType()->toArray());
    }
}
