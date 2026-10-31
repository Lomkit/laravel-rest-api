<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Database\Query\Grammars\Grammar;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Exceptions\UnplannedCheckException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Builder;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\IntegerKeyVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\EitherTeamVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherLineResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class SearchKeysQueryTest extends SearchKeysTestCase
{
    private const array INTEGER_KEY_TYPES = ['int' => NumberedVoucherResource::class, 'integer' => IntegerKeyVoucherResource::class];

    /** @param class-string<Resource> $resource */
    #[Test]
    #[DataProvider('invalidIntegerKeys')]
    public function it_refuses_an_integer_scout_key_that_is_not_an_integer(string $resource, mixed $key): void
    {
        $this->expectExceptionObject(InvalidResourceException::integerScoutKey($resource::modelClass(), $key));

        $this->builder($resource)->searchKeys([3, $key]);
    }

    /** @return iterable<string, array{class-string<Resource>, mixed}> */
    public static function invalidIntegerKeys(): iterable
    {
        foreach (self::INTEGER_KEY_TYPES as $type => $resource) {
            yield "an exponent for the {$type} key type" => [$resource, '1e3'];
            yield "a decimal string for the {$type} key type" => [$resource, '3.5'];
            yield "a float for the {$type} key type" => [$resource, 3.5];
            yield "null for the {$type} key type" => [$resource, null];
            yield "a word for the {$type} key type" => [$resource, 'three'];
        }
    }

    #[Test]
    public function it_refuses_a_string_scout_key_that_is_not_a_scalar(): void
    {
        $this->expectExceptionObject(InvalidResourceException::invalidScoutKey(Voucher::class, ['V-a']));

        $this->builder(VoucherResource::class)->searchKeys(['V-c', ['V-a']]);
    }

    #[Test]
    public function it_refuses_a_model_that_is_not_indexed(): void
    {
        $this->expectExceptionObject(InvalidResourceException::unsearchableModel(VoucherLineResource::$model));

        $this->builder(VoucherLineResource::class)->searchKeys([1]);
    }

    #[Test]
    public function it_checks_that_the_resource_can_be_listed_before_loading_the_keys(): void
    {
        $this->expectException(UnplannedCheckException::class);

        (new Builder($this->context()->resource(VoucherResource::class)))->searchKeys(['V-c']);
    }

    #[Test]
    public function it_binds_string_keys_after_the_perimeter_in_its_own_group(): void
    {
        $query = $this->builder(EitherTeamVoucherResource::class)->searchKeys(['V-d', 'V-c']);

        $this->assertSame(
            'select * from '.$this->table().' where ('.$this->wrap('team').' = ? or '.$this->wrap('team').' = ?) and '
            .$this->wrap('vouchers.code').' in (?, ?) and '.$this->wrap('vouchers.deleted_at').' is null',
            $query->toSql(),
        );
        $this->assertSame(['red', 'blue', 'V-d', 'V-c'], $query->getBindings());
        $this->assertSame([4, 1], $query->get()->modelKeys());
    }

    /** @param class-string<Resource> $resource */
    #[Test]
    #[DataProvider('integerKeyTypes')]
    public function it_writes_integer_keys_into_the_sql_instead_of_binding_them(string $resource): void
    {
        $query = $this->builder($resource)->searchKeys(['3', '1']);

        $this->assertSame(
            'select * from '.$this->table().' where ('.$this->wrap('team').' = ?) and '
            .$this->wrap('vouchers.number').' in (3, 1) and '.$this->wrap('vouchers.deleted_at').' is null',
            $query->toSql(),
        );
        $this->assertSame(['red'], $query->getBindings());
        $this->assertSame([1, 3], $query->get()->modelKeys());
    }

    /** @return iterable<string, array{class-string<Resource>}> */
    public static function integerKeyTypes(): iterable
    {
        foreach (self::INTEGER_KEY_TYPES as $type => $resource) {
            yield "the {$type} key type" => [$resource];
        }
    }

    private function wrap(string $value): string
    {
        return $this->grammar()->wrap($value);
    }

    private function table(): string
    {
        return $this->grammar()->wrapTable('vouchers');
    }

    private function grammar(): Grammar
    {
        return DB::connection()->getQueryGrammar();
    }
}
