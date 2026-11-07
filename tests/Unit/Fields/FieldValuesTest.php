<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\FieldValues;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldValuesTest extends TestCase
{
    /** @param Closure(InputValues): FieldValues $of */
    #[Test]
    #[DataProvider('holders')]
    public function it_reads_the_declared_inputs_the_same_way(Closure $of, string $owner): void
    {
        $fields = $of(self::inputs());

        $this->assertInstanceOf(FieldValues::class, $fields);
        $this->assertSame([PostStatus::Draft, 12, null, null], [$fields->status, $fields->seats, $fields->reason, $fields->note]);
        $this->assertSame([true, true, false], [$fields->has('status'), $fields->has('reason'), $fields->has('note')]);
        $this->assertSame([true, false, false], [$fields->filled('seats'), $fields->filled('reason'), isset($fields->note)]);
        $this->assertSame(['status' => PostStatus::Draft, 'seats' => 12, 'reason' => null], $fields->only('reason', 'note', 'seats', 'status'));
        $this->assertSame(['status' => PostStatus::Draft, 'seats' => 12, 'reason' => null, 'tags' => ['red', 'blue']], $fields->toArray());

        $this->expectExceptionObject(new InvalidFieldException("{$owner}::fields() does not declare the input [seat]."));

        $fields->has('seat');
    }

    /** @return array<string, array{Closure(InputValues): FieldValues, class-string}> */
    public static function holders(): array
    {
        return [
            'action fields' => [fn (InputValues $inputs): FieldValues => ActionFields::of(new Action, $inputs, 'web', null), Action::class],
            'instruction fields' => [fn (InputValues $inputs): FieldValues => InstructionFields::of(new Instruction, $inputs), Instruction::class],
        ];
    }

    private static function inputs(): InputValues
    {
        $fields = FieldSet::of([
            Enum::make('status', PostStatus::class)->default(PostStatus::Draft),
            Integer::make('seats'),
            Text::make('reason')->nullable(),
            Text::make('note'),
            Json::make('tags'),
        ]);

        return InputValues::fromNamed($fields, [
            ['name' => 'tags', 'value' => ['red', 'blue']],
            ['name' => 'reason', 'value' => null],
            ['name' => 'seats', 'value' => '12'],
        ]);
    }
}
