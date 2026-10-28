<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\CurrencyAmount;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Money;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Priority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldTypesTest extends TestCase
{
    /** @return array<string, array{Field, string, list<string>, list<mixed>}> */
    public static function builtInTypes(): array
    {
        $numeric = ['=', '!=', '>', '>=', '<', '<=', 'between', 'not between', 'in', 'not in'];
        $temporal = ['=', '!=', '>', '>=', '<', '<=', 'between', 'not between'];

        return [
            'id' => [ID::make('id'), 'id', ['=', '!=', 'in', 'not in'], ['integer']],
            'text' => [Text::make('title'), 'text', ['=', '!=', 'like', 'not like', 'ilike', 'not ilike', 'in', 'not in'], ['string']],
            'number' => [Number::make('rating'), 'number', $numeric, ['numeric']],
            'integer' => [Integer::make('views'), 'integer', $numeric, ['integer']],
            'boolean' => [Boolean::make('featured'), 'boolean', ['=', '!='], ['boolean']],
            'date' => [Date::make('published_on'), 'date', $temporal, ['date']],
            'datetime' => [DateTime::make('published_at'), 'datetime', $temporal, ['date']],
            'enum' => [Enum::make('priority', Priority::class), 'enum', ['=', '!=', 'in', 'not in'], [Rule::enum(Priority::class)]],
            'json' => [Json::make('metadata'), 'json', [], ['array']],
        ];
    }

    /** @return iterable<string, array{Field, list<string>}> */
    public static function builtInOperators(): iterable
    {
        foreach (self::builtInTypes() as $type => [$field, , $operators]) {
            yield $type => [$field, $operators];
        }
    }

    /** @return array<string, array{Closure(string): Field}> */
    public static function factories(): array
    {
        return [
            'built-in type' => [fn (string $name): Field => Text::make($name)],
            'enum with its own make' => [fn (string $name): Field => Enum::make($name, Priority::class)],
            'app type' => [fn (string $name): Field => Money::make($name)],
            'constructor' => [fn (string $name): Field => new Integer($name)],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function invalidNames(): array
    {
        return [
            'empty' => ['', 'A field name cannot be empty.'],
            'spaces' => ['   ', 'A field name cannot be empty.'],
            'tab and newline' => ["\t\n", 'A field name cannot be empty.'],
            'relation path' => ['author.name', 'The field name [author.name] contains a dot. Dots are reserved for relation paths.'],
            'leading dot' => ['.title', 'The field name [.title] contains a dot. Dots are reserved for relation paths.'],
            'trailing dot' => ['title.', 'The field name [title.] contains a dot. Dots are reserved for relation paths.'],
        ];
    }

    /**
     * @param  list<string>  $operators
     * @param  list<mixed>  $baseRules
     */
    #[Test]
    #[DataProvider('builtInTypes')]
    public function it_describes_each_built_in_type(Field $field, string $type, array $operators, array $baseRules): void
    {
        $this->assertSame($type, $field->type());
        $this->assertSame($operators, $field->operators());
        $this->assertEquals($baseRules, $field->baseRules());
    }

    /** @param list<string> $operators */
    #[Test]
    #[DataProvider('builtInOperators')]
    public function it_supports_its_declared_operators_and_the_null_checks_only_when_nullable(Field $field, array $operators): void
    {
        $this->assertSame($operators, $this->operatorValues($field));
        $this->assertSame([...$operators, 'is null', 'is not null'], $this->operatorValues($field->nullable()));
        $this->assertSame($operators, $this->operatorValues($field->nullable(false)));
    }

    #[Test]
    public function it_returns_the_supported_operators_as_filter_operator_cases(): void
    {
        $this->assertSame(
            [FilterOperator::Equal, FilterOperator::NotEqual, FilterOperator::IsNull, FilterOperator::IsNotNull],
            Boolean::make('featured')->nullable()->supportedOperators(),
        );
    }

    #[Test]
    public function it_does_not_repeat_a_null_check_that_the_type_already_declares(): void
    {
        $field = new class('archived_at') extends Field
        {
            public function operators(): array
            {
                return ['=', 'is null'];
            }

            public function baseRules(): array
            {
                return ['date'];
            }
        };

        $this->assertSame(['=', 'is null'], $this->operatorValues($field));
        $this->assertSame(['=', 'is null', 'is not null'], $this->operatorValues($field->nullable()));
    }

    #[Test]
    public function it_derives_the_type_of_an_app_field_from_its_class_name(): void
    {
        $this->assertSame('money', Money::make('price')->type());
        $this->assertSame('currency_amount', CurrencyAmount::make('total')->type());
    }

    #[Test]
    public function it_takes_the_operators_and_base_rules_of_an_app_type_from_its_class(): void
    {
        $amount = CurrencyAmount::make('total');

        $this->assertInstanceOf(CurrencyAmount::class, $amount);
        $this->assertSame('total', $amount->name);
        $this->assertSame(['=', '>', '>=', '<', '<=', 'between'], $amount->operators());
        $this->assertSame(['numeric', 'min:0'], $amount->baseRules());
        $this->assertSame([
            FilterOperator::Equal,
            FilterOperator::GreaterThan,
            FilterOperator::GreaterThanOrEqual,
            FilterOperator::LessThan,
            FilterOperator::LessThanOrEqual,
            FilterOperator::Between,
        ], $amount->supportedOperators());
    }

    #[Test]
    #[DataProvider('invalidNames')]
    public function it_rejects_an_empty_or_dotted_name(string $name, string $message): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessageMatches('/^'.preg_quote($message, '/').'$/');

        Text::make($name);
    }

    /** @param Closure(string): Field $make */
    #[Test]
    #[DataProvider('factories')]
    public function it_checks_the_name_whatever_the_way_the_field_is_built(Closure $make): void
    {
        $this->assertSame('summary', $make('summary')->name);

        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessageMatches('/^The field name \[author\.summary\] contains a dot\. Dots are reserved for relation paths\.$/');

        $make('author.summary');
    }

    /** @return list<string> */
    private function operatorValues(Field $field): array
    {
        return array_map(fn (FilterOperator $operator): string => $operator->value, $field->supportedOperators());
    }
}
