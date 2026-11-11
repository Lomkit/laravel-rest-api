<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Carbon\CarbonImmutable;
use Closure;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\CastValues;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CastValuesTest extends PackageTestCase
{
    #[Test]
    #[DataProvider('inputs')]
    public function it_tells_a_missing_input_from_a_null_one(string $name, mixed $value, bool $has, bool $filled): void
    {
        $values = self::values();

        $this->assertSame([$value, $has, $filled], [$values->get($name), $values->has($name), $values->filled($name)]);
    }

    /** @return array<string, array{string, mixed, bool, bool}> */
    public static function inputs(): array
    {
        return [
            'sent with a value' => ['seats', 12, true, true],
            'sent as null' => ['reason', null, true, false],
            'not sent, with a default' => ['status', PostStatus::Draft, true, true],
            'not sent, without a default' => ['note', null, false, false],
        ];
    }

    #[Test]
    public function it_gives_the_cast_values_in_declaration_order(): void
    {
        $values = self::values();

        $this->assertSame(['starts_at', 'status', 'seats', 'reason', 'tags'], array_keys($values->toArray()));
        $this->assertEquals(CarbonImmutable::parse('2026-12-01 10:00:00'), $values->get('starts_at'));
        $this->assertSame([PostStatus::Draft, 12, null, ['red', 'blue']], array_values($values->only('tags', 'reason', 'note', 'seats', 'status')));
        $this->assertSame(['seats', 'reason'], array_keys($values->only('reason', 'note', 'seats')));
    }

    /** @param Closure(CastValues): mixed $read */
    #[Test]
    #[DataProvider('readers')]
    public function it_refuses_a_name_that_is_not_declared(Closure $read): void
    {
        $this->expectExceptionObject(new InvalidFieldException('App\Rest\Actions\ExtendSubscriptions::fields() does not declare the input [seat].'));

        $read(self::values());
    }

    /** @return array<string, array{Closure(CastValues): mixed}> */
    public static function readers(): array
    {
        return [
            'get' => [fn (CastValues $values): mixed => $values->get('seat')],
            'has' => [fn (CastValues $values): bool => $values->has('seat')],
            'filled' => [fn (CastValues $values): bool => $values->filled('seat')],
            'only' => [fn (CastValues $values): array => $values->only('seats', 'seat')],
        ];
    }

    private static function values(): CastValues
    {
        $fields = FieldSet::of([
            DateTime::make('starts_at'),
            Enum::make('status', PostStatus::class)->default(PostStatus::Draft),
            Integer::make('seats'),
            Text::make('reason')->nullable(),
            Text::make('note'),
            Json::make('tags'),
        ]);

        return CastValues::of(InputValues::fromNamed($fields, [
            ['name' => 'tags', 'value' => ['red', 'blue']],
            ['name' => 'reason', 'value' => null],
            ['name' => 'seats', 'value' => '12'],
            ['name' => 'starts_at', 'value' => '2026-12-01 10:00:00'],
        ]), 'App\Rest\Actions\ExtendSubscriptions::fields()');
    }
}
