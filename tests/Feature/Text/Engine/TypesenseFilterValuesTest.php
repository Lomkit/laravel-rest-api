<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\TitledVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class TypesenseFilterValuesTest extends EngineSearchTestCase
{
    private FilterCapturingTypesenseEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('titled-vouchers', TitledVoucherController::class)->register();
            Rest::resource('red-vouchers', RedVoucherController::class)->register();
        });
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
        $this->engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($this->engine);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
    }

    /** @param array<string, mixed> $filter */
    #[Test]
    #[DataProvider('literals')]
    public function it_sends_filter_values_to_typesense_as_literals(string $resource, array $filter, string $sent): void
    {
        $response = $this->search($resource, ['text' => ['value' => 'report'], 'filters' => [$filter]]);

        $response->assertOk();
        $this->assertSame([$sent], $this->engine->filters);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function literals(): array
    {
        return [
            'a title' => ['titled-vouchers', ['field' => 'title', 'value' => 'Report alpha'], 'title:=`Report alpha` && team:=[`red`]'],
            'a title holding ||' => ['titled-vouchers', ['field' => 'title', 'value' => 'x || team:=blue'], 'title:=`x || team:=blue` && team:=[`red`]'],
            'a title holding &&' => ['titled-vouchers', ['field' => 'title', 'value' => 'x && team:=blue'], 'title:=`x && team:=blue` && team:=[`red`]'],
            'a code holding parentheses' => ['titled-vouchers', ['field' => 'code', 'value' => 'x) || (team:=blue'], 'code:=`x) || (team:=blue` && team:=[`red`]'],
            'a title holding a colon and brackets' => ['titled-vouchers', ['field' => 'title', 'value' => 'a:b [c] || team:=[blue]'], 'title:=`a:b [c] || team:=[blue]` && team:=[`red`]'],
            'a title holding a colon before brackets' => ['titled-vouchers', ['field' => 'title', 'value' => 'x:[a]'], 'title:=`x:[a]` && team:=[`red`]'],
            'a title holding a trailing backslash' => ['titled-vouchers', ['field' => 'title', 'value' => 'Report trailing\\'], 'title:=`Report trailing\\ ` && team:=[`red`]'],
            'a different title' => ['titled-vouchers', ['field' => 'title', 'operator' => '!=', 'value' => 'zz || team:!=red'], 'title:!=`zz || team:!=red` && team:=[`red`]'],
            'a list of titles' => ['titled-vouchers', ['field' => 'title', 'operator' => 'in', 'value' => ['a', 'b] || team:!=[red']], 'team:=[`red`] && title:=[`a`, `b] || team:!=[red`]'],
            'an excluded list of titles' => ['titled-vouchers', ['field' => 'title', 'operator' => 'not in', 'value' => ['a', 'b] || team:=[blue']], 'team:=[`red`] && title:!=[`a`, `b] || team:=[blue`]'],
            'a title on a fixed perimeter' => ['red-vouchers', ['field' => 'title', 'value' => 'x || team:=blue'], 'team:=`red` && title:=`x || team:=blue`'],
            'a number as a string' => ['titled-vouchers', ['field' => 'number', 'value' => '3'], 'number:=3 && team:=[`red`]'],
            'a decimal' => ['titled-vouchers', ['field' => 'price', 'operator' => '>=', 'value' => 1.5], 'price:>=1.5 && team:=[`red`]'],
            'a boolean' => ['titled-vouchers', ['field' => 'active', 'value' => true], 'active:=true && team:=[`red`]'],
            'a list of numbers' => ['titled-vouchers', ['field' => 'number', 'operator' => 'in', 'value' => [1, '3']], 'team:=[`red`] && number:=[1, 3]'],
            'a number written as a title' => ['titled-vouchers', ['field' => 'title', 'value' => '1e5'], 'title:=`1e5` && team:=[`red`]'],
        ];
    }
}
