<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Details\FieldDetails;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\StartsWithCapital;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldDetailsTest extends TestCase
{
    private const string T8 = '["=","!=","like","not like","ilike","not ilike","in","not in"]';

    private const string N10 = '["=","!=",">",">=","<","<=","between","not between","in","not in"]';

    private const string N12 = '["=","!=",">",">=","<","<=","between","not between","in","not in","is null","is not null"]';

    private const string D8 = '["=","!=",">",">=","<","<=","between","not between"]';

    private const string ID4 = '["=","!=","in","not in"]';

    #[Test]
    public function it_describes_the_reference_price_entry_byte_for_byte(): void
    {
        $this->assertSame(
            '{"name":"price","type":"number","nullable":true,"select":true,"sort":true,"filter":{"operators":["=","!=",">",">=","<","<=","between","not between","in","not in","is null","is not null"]},"create":true,"update":true,"rules":{"all":["nullable","numeric"],"create":[],"update":[]},"meta":{}}',
            json_encode(FieldDetails::of(Number::make('price')->nullable())),
        );
    }

    #[Test]
    #[DataProvider('builtInTypes')]
    public function it_describes_each_built_in_type(Field $field, string $expected): void
    {
        $this->assertSame($expected, json_encode(FieldDetails::of($field)));
    }

    /** @return array<string, array{Field, string}> */
    public static function builtInTypes(): array
    {
        return [
            'an id' => [ID::make('id'), '{"name":"id","type":"id","nullable":false,"select":true,"sort":true,"filter":{"operators":'.self::ID4.'},"create":false,"update":false,"rules":{"all":["integer"],"create":[],"update":[]},"meta":{}}'],
            'a text' => [Text::make('name'), '{"name":"name","type":"text","nullable":false,"select":true,"sort":true,"filter":{"operators":'.self::T8.'},"create":true,"update":true,"rules":{"all":["string"],"create":[],"update":[]},"meta":{}}'],
            'an integer' => [Integer::make('views'), '{"name":"views","type":"integer","nullable":false,"select":true,"sort":true,"filter":{"operators":'.self::N10.'},"create":true,"update":true,"rules":{"all":["integer"],"create":[],"update":[]},"meta":{}}'],
            'a number' => [Number::make('rating'), '{"name":"rating","type":"number","nullable":false,"select":true,"sort":true,"filter":{"operators":'.self::N10.'},"create":true,"update":true,"rules":{"all":["numeric"],"create":[],"update":[]},"meta":{}}'],
            'a boolean' => [Boolean::make('featured'), '{"name":"featured","type":"boolean","nullable":false,"select":true,"sort":true,"filter":{"operators":["=","!="]},"create":true,"update":true,"rules":{"all":["boolean"],"create":[],"update":[]},"meta":{}}'],
            'a date' => [Date::make('published_on'), '{"name":"published_on","type":"date","nullable":false,"select":true,"sort":true,"filter":{"operators":'.self::D8.'},"create":true,"update":true,"rules":{"all":["date"],"create":[],"update":[]},"meta":{}}'],
            'a date and time' => [DateTime::make('published_at'), '{"name":"published_at","type":"datetime","nullable":false,"select":true,"sort":true,"filter":{"operators":'.self::D8.'},"create":true,"update":true,"rules":{"all":["date"],"create":[],"update":[]},"meta":{}}'],
            'an enum' => [Enum::make('status', PostStatus::class), '{"name":"status","type":"enum","nullable":false,"select":true,"sort":true,"filter":{"operators":'.self::ID4.'},"create":true,"update":true,"rules":{"all":["enum"],"create":[],"update":[]},"meta":{},"values":["draft","review","published"]}'],
            'a json' => [Json::make('metadata'), '{"name":"metadata","type":"json","nullable":false,"select":true,"sort":false,"filter":{"operators":[]},"create":true,"update":true,"rules":{"all":["array"],"create":[],"update":[]},"meta":{}}'],
        ];
    }

    #[Test]
    #[DataProvider('allowances')]
    public function it_describes_what_a_field_allows(Field $field, string $expected): void
    {
        $details = FieldDetails::of($field)->jsonSerialize();

        $this->assertSame($expected, json_encode(array_intersect_key($details, array_flip(['select', 'sort', 'filter', 'create', 'update']))));
    }

    /** @return array<string, array{Field, string}> */
    public static function allowances(): array
    {
        $virtual = Integer::make('score')
            ->virtual()
            ->fillUsing(function (Model $model, mixed $value): void {})
            ->sortUsing(function (Builder $query, string $direction): void {})
            ->filterUsing(function (Builder $query, string $operator, mixed $value): void {});

        return [
            'a write-only field' => [Text::make('secret')->writeOnly(), '{"select":false,"sort":false,"filter":{"operators":[]},"create":true,"update":true}'],
            'a read-only field' => [Text::make('slug')->readonly(), '{"select":true,"sort":true,"filter":{"operators":'.self::T8.'},"create":false,"update":false}'],
            'a field set on create only' => [Text::make('slug')->updatable(false), '{"select":true,"sort":true,"filter":{"operators":'.self::T8.'},"create":true,"update":false}'],
            'a field set on update only' => [Text::make('slug')->creatable(false), '{"select":true,"sort":true,"filter":{"operators":'.self::T8.'},"create":false,"update":true}'],
            'an unselectable field' => [Text::make('slug')->selectable(false), '{"select":false,"sort":true,"filter":{"operators":'.self::T8.'},"create":true,"update":true}'],
            'an unsortable field' => [Integer::make('views')->sortable(false), '{"select":true,"sort":false,"filter":{"operators":'.self::N10.'},"create":true,"update":true}'],
            'an unfilterable field' => [Integer::make('views')->filterable(false), '{"select":true,"sort":true,"filter":{"operators":[]},"create":true,"update":true}'],
            'a field with chosen operators' => [Integer::make('views')->filterable(['=', '>']), '{"select":true,"sort":true,"filter":{"operators":["=",">"]},"create":true,"update":true}'],
            'a virtual field' => [Integer::make('score')->virtual(), '{"select":false,"sort":false,"filter":{"operators":[]},"create":false,"update":false}'],
            'a virtual field with callbacks' => [$virtual, '{"select":false,"sort":true,"filter":{"operators":'.self::N10.'},"create":true,"update":true}'],
        ];
    }

    #[Test]
    public function it_describes_the_rules_of_each_write_operation(): void
    {
        $closure = function (string $attribute, mixed $value, Closure $fail): void {};
        $field = Text::make('title')
            ->rules('max:60')
            ->creationRules('required', 'unique:posts')
            ->updateRules('filled', new StartsWithCapital, $closure);

        $this->assertSame(
            '{"all":["string","max:60"],"create":["required","unique:posts"],"update":["filled","starts_with_capital"]}',
            json_encode(FieldDetails::of($field)->jsonSerialize()['rules']),
        );
    }

    #[Test]
    public function it_encodes_the_meta_as_an_object(): void
    {
        $this->assertSame('{}', json_encode(FieldDetails::of(Text::make('title'))->jsonSerialize()['meta']));
        $this->assertSame('{"label":"A"}', json_encode(FieldDetails::of(Text::make('title')->meta(['label' => 'A']))->jsonSerialize()['meta']));
    }

    #[Test]
    public function it_describes_pivot_fields_without_sort(): void
    {
        $this->assertSame(
            '['
            .'{"name":"position","type":"integer","nullable":true,"select":true,"sort":false,"filter":{"operators":'.self::N12.'},"create":true,"update":true,"rules":{"all":["nullable","integer"],"create":[],"update":[]},"meta":{}},'
            .'{"name":"wall","type":"text","nullable":false,"select":true,"sort":false,"filter":{"operators":'.self::T8.'},"create":true,"update":false,"rules":{"all":["string"],"create":[],"update":[]},"meta":{}},'
            .'{"name":"note","type":"text","nullable":false,"select":false,"sort":false,"filter":{"operators":[]},"create":true,"update":true,"rules":{"all":["string"],"create":[],"update":[]},"meta":{}},'
            .'{"name":"created_at","type":"datetime","nullable":false,"select":true,"sort":false,"filter":{"operators":'.self::D8.'},"create":false,"update":false,"rules":{"all":["date"],"create":[],"update":[]},"meta":{}}'
            .']',
            json_encode(FieldDetails::forPivotFields($this->pivotFields(), true)),
        );
    }

    #[Test]
    public function it_describes_the_pivot_fields_of_a_relation_that_is_not_many_to_many(): void
    {
        $this->assertSame(
            '['
            .'{"name":"position","type":"integer","nullable":true,"select":false,"sort":false,"filter":{"operators":[]},"create":true,"update":true,"rules":{"all":["nullable","integer"],"create":[],"update":[]},"meta":{}},'
            .'{"name":"wall","type":"text","nullable":false,"select":false,"sort":false,"filter":{"operators":[]},"create":true,"update":false,"rules":{"all":["string"],"create":[],"update":[]},"meta":{}},'
            .'{"name":"note","type":"text","nullable":false,"select":false,"sort":false,"filter":{"operators":[]},"create":true,"update":true,"rules":{"all":["string"],"create":[],"update":[]},"meta":{}},'
            .'{"name":"created_at","type":"datetime","nullable":false,"select":false,"sort":false,"filter":{"operators":[]},"create":false,"update":false,"rules":{"all":["date"],"create":[],"update":[]},"meta":{}}'
            .']',
            json_encode(FieldDetails::forPivotFields($this->pivotFields(), false)),
        );
    }

    #[Test]
    public function it_describes_every_field_of_a_set_in_declared_order(): void
    {
        $this->assertSame(
            '[{"name":"title","type":"text","nullable":false,"select":true,"sort":true,"filter":{"operators":["=","!=","like","not like","ilike","not ilike","in","not in"]},"create":true,"update":true,"rules":{"all":["string"],"create":[],"update":[]},"meta":{}},{"name":"featured","type":"boolean","nullable":false,"select":true,"sort":true,"filter":{"operators":["=","!="]},"create":true,"update":true,"rules":{"all":["boolean"],"create":[],"update":[]},"meta":{}}]',
            json_encode(FieldDetails::forFields(FieldSet::of([Text::make('title'), Boolean::make('featured')]))),
        );
    }

    private function pivotFields(): FieldSet
    {
        return FieldSet::forPivot([
            Integer::make('position')->nullable(),
            Text::make('wall')->updatable(false),
            Text::make('note')->writeOnly(),
            DateTime::make('created_at')->readonly(),
        ], 'pivotFields()');
    }
}
