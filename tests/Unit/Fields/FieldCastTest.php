<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Support\Carbon;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Money;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Priority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Stringable;
use ValueError;

class FieldCastTest extends TestCase
{
    /** @return array<string, array{Field, mixed, mixed}> */
    public static function scalarCasts(): array
    {
        return [
            'integer from a string' => [Integer::make('views'), '42', 42],
            'integer from a signed string' => [Integer::make('views'), '-3', -3],
            'integer kept' => [Integer::make('views'), 7, 7],
            'integer left alone when not an integer' => [Integer::make('views'), '10.5', '10.5'],
            'boolean from true' => [Boolean::make('featured'), true, true],
            'boolean from false' => [Boolean::make('featured'), false, false],
            'boolean from 1' => [Boolean::make('featured'), 1, true],
            'boolean from 0' => [Boolean::make('featured'), 0, false],
            'boolean from "1"' => [Boolean::make('featured'), '1', true],
            'boolean from "0"' => [Boolean::make('featured'), '0', false],
            'boolean left alone when not a boolean' => [Boolean::make('featured'), 'maybe', 'maybe'],
            'text from an integer' => [Text::make('title'), 42, '42'],
            'text kept' => [Text::make('title'), 'Hello', 'Hello'],
            'text left alone when not scalar' => [Text::make('title'), ['Hello'], ['Hello']],
            'number string kept' => [Number::make('rating'), '4.50', '4.50'],
            'number integer kept' => [Number::make('rating'), 4, 4],
            'json kept' => [Json::make('metadata'), ['tags' => ['php']], ['tags' => ['php']]],
            'app type kept' => [Money::make('price'), '9.99', '9.99'],
            'int id from a string' => [ID::make('id'), '12', 12],
            'string id from an integer' => [ID::make('id')->keyType('string'), 12, '12'],
            'uuid id kept' => [ID::make('id')->keyType('uuid'), '0195f2a4-7c1e-7b3a-9d4e-1f2a3b4c5d6e', '0195f2a4-7c1e-7b3a-9d4e-1f2a3b4c5d6e'],
            'ulid id kept' => [ID::make('id')->keyType('ulid'), '01JQ8Z3K4M5N6P7Q8R9S0T1V2W', '01JQ8Z3K4M5N6P7Q8R9S0T1V2W'],
        ];
    }

    #[Test]
    #[DataProvider('scalarCasts')]
    public function it_casts_a_value_by_the_type_of_the_field(Field $field, mixed $value, mixed $expected): void
    {
        $this->assertSame($expected, $field->cast($value));
    }

    /** @return array<string, array{Field}> */
    public static function everyType(): array
    {
        return [
            'id' => [ID::make('id')],
            'text' => [Text::make('title')],
            'number' => [Number::make('rating')],
            'integer' => [Integer::make('views')],
            'boolean' => [Boolean::make('featured')],
            'date' => [Date::make('published_on')],
            'datetime' => [DateTime::make('published_at')],
            'enum' => [Enum::make('priority', Priority::class)],
            'json' => [Json::make('metadata')],
        ];
    }

    #[Test]
    #[DataProvider('everyType')]
    public function it_keeps_null_as_null(Field $field): void
    {
        $this->assertNull($field->cast(null));
    }

    #[Test]
    public function it_casts_a_date_to_the_start_of_its_day(): void
    {
        $date = Date::make('published_on')->cast('2026-01-01 15:30:45');

        $this->assertInstanceOf(CarbonImmutable::class, $date);
        $this->assertSame('2026-01-01 00:00:00', $date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_casts_a_datetime_and_keeps_its_time(): void
    {
        $date = DateTime::make('published_at')->cast('2026-01-01 15:30:45');

        $this->assertInstanceOf(CarbonImmutable::class, $date);
        $this->assertSame('2026-01-01 15:30:45', $date->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_casts_a_date_object_without_changing_it(): void
    {
        $original = Carbon::parse('2026-03-04 05:06:07');

        $date = Date::make('published_on')->cast($original);
        $dateTime = DateTime::make('published_at')->cast(new DateTimeImmutable('2026-03-04 05:06:07'));

        $this->assertInstanceOf(CarbonImmutable::class, $date);
        $this->assertSame('2026-03-04 00:00:00', $date->format('Y-m-d H:i:s'));
        $this->assertSame('2026-03-04 05:06:07', $original->format('Y-m-d H:i:s'));
        $this->assertInstanceOf(CarbonImmutable::class, $dateTime);
        $this->assertSame('2026-03-04 05:06:07', $dateTime->format('Y-m-d H:i:s'));
    }

    #[Test]
    public function it_leaves_a_date_value_alone_when_it_is_not_a_date(): void
    {
        $this->assertSame(['2026-01-01'], Date::make('published_on')->cast(['2026-01-01']));
    }

    #[Test]
    public function it_casts_an_enum_value_through_the_enum(): void
    {
        $this->assertSame(Priority::Normal, Enum::make('priority', Priority::class)->cast(2));
        $this->assertSame(Priority::High, Enum::make('priority', Priority::class)->cast('3'));
        $this->assertSame(PostStatus::Review, Enum::make('status', PostStatus::class)->cast('review'));
        $this->assertSame(PostStatus::Draft, Enum::make('status', PostStatus::class)->cast(PostStatus::Draft));
    }

    /** @return array<string, array{mixed, Priority}> */
    public static function coercedEnumValues(): array
    {
        return [
            'true' => [true, Priority::Low],
            'a whole float' => [2.0, Priority::Normal],
            'a numeric string with a fraction' => ['3.0', Priority::High],
        ];
    }

    #[Test]
    #[DataProvider('coercedEnumValues')]
    public function it_casts_a_value_the_enum_rule_accepts_to_the_case_the_rule_found(mixed $value, Priority $expected): void
    {
        $this->assertSame($expected, Priority::tryFrom($value));
        $this->assertSame($expected, Enum::make('priority', Priority::class)->cast($value));
    }

    #[Test]
    public function it_rejects_an_enum_value_that_is_not_a_case(): void
    {
        $this->expectException(ValueError::class);

        Enum::make('status', PostStatus::class)->cast('archived');
    }

    #[Test]
    public function it_casts_a_stringable_text_or_string_id_to_a_string(): void
    {
        $slug = new class implements Stringable
        {
            #[\Override]
            public function __toString(): string
            {
                return 'hello-world';
            }
        };

        $this->assertSame('hello-world', Text::make('slug')->cast($slug));
        $this->assertSame('hello-world', ID::make('slug')->keyType('string')->cast($slug));
    }
}
