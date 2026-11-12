<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Carbon\CarbonImmutable;
use Closure;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\FieldValues;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Priority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class FieldReadersTest extends PackageTestCase
{
    private const string OWNER = Action::class.'::fields()';

    /** @return array<string, array{Closure(FieldValues): mixed, mixed}> */
    public static function readings(): array
    {
        return [
            'string' => [fn (FieldValues $fields): ?string => $fields->string('reason'), 'Late delivery'],
            'integer' => [fn (FieldValues $fields): ?int => $fields->integer('seats'), 12],
            'float' => [fn (FieldValues $fields): ?float => $fields->float('rate'), 1.5],
            'float from an integer' => [fn (FieldValues $fields): ?float => $fields->float('ratio'), 2.0],
            'float from a numeric string' => [fn (FieldValues $fields): ?float => $fields->float('price'), 19.99],
            'integer from an integer string' => [fn (FieldValues $fields): ?int => $fields->integer('code'), 3],
            'boolean' => [fn (FieldValues $fields): ?bool => $fields->boolean('notify'), false],
            'enum' => [fn (FieldValues $fields): ?PostStatus => $fields->enum('status', PostStatus::class), PostStatus::Review],
            'array' => [fn (FieldValues $fields): ?array => $fields->array('tags'), ['red', 'blue']],
        ];
    }

    #[Test]
    #[DataProvider('readings')]
    public function it_returns_the_cast_value_in_the_type_of_the_reader(Closure $read, mixed $expected): void
    {
        $this->assertSame($expected, $read(self::actionFields()));
    }

    #[Test]
    public function it_reads_a_date_field_as_an_immutable_date(): void
    {
        $date = self::actionFields()->date('starts_on');

        $this->assertInstanceOf(CarbonImmutable::class, $date);
        $this->assertSame('2026-03-01 00:00:00 UTC', $date->format('Y-m-d H:i:s T'));
    }

    #[Test]
    public function it_reads_a_date_time_field_as_an_immutable_date_in_the_application_timezone(): void
    {
        $date = self::actionFields()->date('starts_at');

        $this->assertInstanceOf(CarbonImmutable::class, $date);
        $this->assertSame('2026-03-01 07:30:00 UTC', $date->format('Y-m-d H:i:s T'));
    }

    /** @return array<string, array{Closure(FieldValues, string): mixed}> */
    public static function readers(): array
    {
        return [
            'string' => [fn (FieldValues $fields, string $name): ?string => $fields->string($name)],
            'integer' => [fn (FieldValues $fields, string $name): ?int => $fields->integer($name)],
            'float' => [fn (FieldValues $fields, string $name): ?float => $fields->float($name)],
            'boolean' => [fn (FieldValues $fields, string $name): ?bool => $fields->boolean($name)],
            'date' => [fn (FieldValues $fields, string $name): ?CarbonImmutable => $fields->date($name)],
            'enum' => [fn (FieldValues $fields, string $name): ?PostStatus => $fields->enum($name, PostStatus::class)],
            'array' => [fn (FieldValues $fields, string $name): ?array => $fields->array($name)],
        ];
    }

    #[Test]
    #[DataProvider('readers')]
    public function it_returns_null_for_an_input_sent_as_null(Closure $read): void
    {
        $this->assertNull($read(self::actionFields(), 'note'));
    }

    #[Test]
    #[DataProvider('readers')]
    public function it_returns_null_for_an_input_not_sent_and_without_default(Closure $read): void
    {
        $this->assertNull($read(self::actionFields(), 'comment'));
    }

    #[Test]
    #[DataProvider('readers')]
    public function it_throws_for_an_undeclared_input(Closure $read): void
    {
        $this->expectExceptionObject(new InvalidFieldException('Lomkit\Rest\Actions\Action::fields() does not declare the input [missing].'));

        $read(self::actionFields(), 'missing');
    }

    /** @return array<string, array{Closure(FieldValues): mixed, string}> */
    public static function mismatches(): array
    {
        return [
            'string' => [fn (FieldValues $fields): ?string => $fields->string('seats'), 'The input [seats] of '.self::OWNER.' is [int], not [string].'],
            'integer' => [fn (FieldValues $fields): ?int => $fields->integer('rate'), 'The input [rate] of '.self::OWNER.' is [float], not [int].'],
            'integer from a decimal string' => [fn (FieldValues $fields): ?int => $fields->integer('price'), 'The input [price] of '.self::OWNER.' is [string], not [int].'],
            'integer from an exponent string' => [fn (FieldValues $fields): ?int => $fields->integer('exponent'), 'The input [exponent] of '.self::OWNER.' is [string], not [int].'],
            'float' => [fn (FieldValues $fields): ?float => $fields->float('reason'), 'The input [reason] of '.self::OWNER.' is [string], not [float].'],
            'boolean' => [fn (FieldValues $fields): ?bool => $fields->boolean('seats'), 'The input [seats] of '.self::OWNER.' is [int], not [bool].'],
            'date' => [fn (FieldValues $fields): ?CarbonImmutable => $fields->date('reason'), 'The input [reason] of '.self::OWNER.' is [string], not [Carbon\CarbonImmutable].'],
            'enum' => [fn (FieldValues $fields): ?PostStatus => $fields->enum('reason', PostStatus::class), 'The input [reason] of '.self::OWNER.' is [string], not [Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus].'],
            'enum of another enum' => [fn (FieldValues $fields): ?Priority => $fields->enum('status', Priority::class), 'The input [status] of '.self::OWNER.' is [Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus::Review], not [Lomkit\Rest\Tests\Unit\Fields\Stubs\Priority].'],
            'array' => [fn (FieldValues $fields): ?array => $fields->array('reason'), 'The input [reason] of '.self::OWNER.' is [string], not [array].'],
        ];
    }

    #[Test]
    #[DataProvider('mismatches')]
    public function it_throws_for_a_value_of_another_type(Closure $read, string $message): void
    {
        $this->expectExceptionObject(new InvalidFieldException($message));

        $read(self::actionFields());
    }

    /** @return array<string, array{Closure(FieldValues): mixed, mixed}> */
    public static function requiredReadings(): array
    {
        return [
            'string' => [fn (FieldValues $fields): string => $fields->string('reason', required: true), 'Late delivery'],
            'integer' => [fn (FieldValues $fields): int => $fields->integer('code', required: true), 3],
            'float' => [fn (FieldValues $fields): float => $fields->float('price', required: true), 19.99],
            'boolean' => [fn (FieldValues $fields): bool => $fields->boolean('notify', required: true), false],
            'date' => [fn (FieldValues $fields): string => $fields->date('starts_on', required: true)->toDateString(), '2026-03-01'],
            'enum' => [fn (FieldValues $fields): PostStatus => $fields->enum('status', PostStatus::class, required: true), PostStatus::Review],
            'array' => [fn (FieldValues $fields): array => $fields->array('tags', required: true), ['red', 'blue']],
        ];
    }

    #[Test]
    #[DataProvider('requiredReadings')]
    public function it_returns_the_cast_value_of_a_required_read(Closure $read, mixed $expected): void
    {
        $this->assertSame($expected, $read(self::actionFields()));
    }

    /** @return array<string, array{Closure(FieldValues, string): mixed}> */
    public static function requiredReaders(): array
    {
        return [
            'string' => [fn (FieldValues $fields, string $name): string => $fields->string($name, required: true)],
            'integer' => [fn (FieldValues $fields, string $name): int => $fields->integer($name, required: true)],
            'float' => [fn (FieldValues $fields, string $name): float => $fields->float($name, required: true)],
            'boolean' => [fn (FieldValues $fields, string $name): bool => $fields->boolean($name, required: true)],
            'date' => [fn (FieldValues $fields, string $name): CarbonImmutable => $fields->date($name, required: true)],
            'enum' => [fn (FieldValues $fields, string $name): PostStatus => $fields->enum($name, PostStatus::class, required: true)],
            'array' => [fn (FieldValues $fields, string $name): array => $fields->array($name, required: true)],
        ];
    }

    #[Test]
    #[DataProvider('requiredReaders')]
    public function it_throws_for_a_required_read_of_an_input_sent_as_null(Closure $read): void
    {
        $this->expectExceptionObject(new InvalidFieldException('The input [note] of '.self::OWNER.' is required, but its value is null. Add the required rule to the input.'));

        $read(self::actionFields(), 'note');
    }

    #[Test]
    #[DataProvider('requiredReaders')]
    public function it_throws_for_a_required_read_of_an_input_not_sent_and_without_default(Closure $read): void
    {
        $this->expectExceptionObject(new InvalidFieldException('The input [comment] of '.self::OWNER.' is required, but its value is null. Add the required rule to the input.'));

        $read(self::actionFields(), 'comment');
    }

    #[Test]
    #[DataProvider('requiredReaders')]
    public function it_checks_the_declaration_before_a_required_read(Closure $read): void
    {
        $this->expectExceptionObject(new InvalidFieldException(self::OWNER.' does not declare the input [missing].'));

        $read(self::actionFields(), 'missing');
    }

    /** @return array<string, array{Closure(FieldValues, string): mixed}> */
    public static function optionalReaders(): array
    {
        return [
            'string' => [fn (FieldValues $fields, string $name): ?string => $fields->string($name, required: false)],
            'integer' => [fn (FieldValues $fields, string $name): ?int => $fields->integer($name, required: false)],
            'float' => [fn (FieldValues $fields, string $name): ?float => $fields->float($name, required: false)],
            'boolean' => [fn (FieldValues $fields, string $name): ?bool => $fields->boolean($name, required: false)],
            'date' => [fn (FieldValues $fields, string $name): ?CarbonImmutable => $fields->date($name, required: false)],
            'enum' => [fn (FieldValues $fields, string $name): ?PostStatus => $fields->enum($name, PostStatus::class, required: false)],
            'array' => [fn (FieldValues $fields, string $name): ?array => $fields->array($name, required: false)],
        ];
    }

    #[Test]
    #[DataProvider('optionalReaders')]
    public function it_returns_null_for_a_read_that_is_not_required(Closure $read): void
    {
        $this->assertSame([null, null], [$read(self::actionFields(), 'note'), $read(self::actionFields(), 'comment')]);
    }

    #[Test]
    public function it_keeps_the_type_check_of_a_required_read(): void
    {
        $this->expectExceptionObject(new InvalidFieldException('The input [rate] of '.self::OWNER.' is [float], not [int].'));

        self::actionFields()->integer('rate', required: true);
    }

    #[Test]
    public function it_reads_required_instruction_fields_the_same_way(): void
    {
        $fields = InstructionFields::of(new Instruction, self::inputs());

        $this->assertSame(12, $fields->integer('seats', required: true));

        $this->expectExceptionObject(new InvalidFieldException('The input [note] of Lomkit\Rest\Instructions\Instruction::fields() is required, but its value is null. Add the required rule to the input.'));

        $fields->string('note', required: true);
    }

    #[Test]
    public function it_reads_instruction_fields_the_same_way(): void
    {
        $fields = InstructionFields::of(new Instruction, self::inputs());

        $this->assertSame([12, PostStatus::Review, null], [$fields->integer('seats'), $fields->enum('status', PostStatus::class), $fields->string('comment')]);

        $this->expectExceptionObject(new InvalidFieldException('The input [seats] of Lomkit\Rest\Instructions\Instruction::fields() is [int], not [string].'));

        $fields->string('seats');
    }

    #[Test]
    public function it_keeps_reading_the_inputs_as_properties(): void
    {
        $fields = self::actionFields();

        $this->assertSame(['Late delivery', 12, 1.5, '19.99', '2026-03-01'], [$fields->reason, $fields->seats, $fields->rate, $fields->price, $fields->starts_on?->toDateString()]);
    }

    private static function actionFields(): ActionFields
    {
        return ActionFields::of(new Action, self::inputs(), 'web', null);
    }

    private static function inputs(): InputValues
    {
        $fields = FieldSet::of([
            Text::make('reason'),
            Text::make('note')->nullable(),
            Text::make('comment'),
            Integer::make('seats'),
            Number::make('rate'),
            Number::make('ratio'),
            Number::make('price'),
            Text::make('code'),
            Text::make('exponent'),
            Boolean::make('notify'),
            Date::make('starts_on'),
            DateTime::make('starts_at'),
            Enum::make('status', PostStatus::class),
            Json::make('tags'),
        ]);

        return InputValues::fromNamed($fields, [
            ['name' => 'reason', 'value' => 'Late delivery'],
            ['name' => 'note', 'value' => null],
            ['name' => 'seats', 'value' => '12'],
            ['name' => 'rate', 'value' => 1.5],
            ['name' => 'ratio', 'value' => 2],
            ['name' => 'price', 'value' => '19.99'],
            ['name' => 'code', 'value' => '3'],
            ['name' => 'exponent', 'value' => '1e3'],
            ['name' => 'notify', 'value' => '0'],
            ['name' => 'starts_on', 'value' => '2026-03-01'],
            ['name' => 'starts_at', 'value' => '2026-03-01T09:30:00+02:00'],
            ['name' => 'status', 'value' => 'review'],
            ['name' => 'tags', 'value' => ['red', 'blue']],
        ]);
    }
}
