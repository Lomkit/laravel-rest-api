<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Turbopuffer;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CheapEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderGroupEngineRestriction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\GroupedReaderVoucherController;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
#[Group('turbopuffer')]
#[WithConfig('rest.extensions', [ReadersExtension::class, CheapEngineRestriction::class])]
class TurbopufferGroupTest extends TurbopufferTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(fn () => Rest::resource('grouped-reader-vouchers', GroupedReaderVoucherController::class));
    }

    #[Test]
    public function it_ands_the_client_group_with_the_string_list_the_perimeter_and_the_restriction(): void
    {
        $this->actingAs(new GenericUser(['id' => 'ann']));
        $this->rows(['V-c'], 1);

        $either = $this->search('grouped-reader-vouchers', ['filters' => [['field' => 'number', 'value' => 3], ['field' => 'number', 'value' => 4, 'type' => 'or']]]);
        $nested = $this->search('grouped-reader-vouchers', ['filters' => [['field' => 'active', 'value' => true], ['nested' => [['field' => 'number', 'value' => 3], ['field' => 'price', 'operator' => '>', 'value' => 20, 'type' => 'or']]]]]);

        $either->assertOk()->assertJsonPath('data.*.id', [1]);
        $nested->assertOk()->assertJsonPath('data.*.id', [1]);
        $flat = ['And', [['__soft_deleted', 'Eq', 0], ['price', 'Lt', 35], ['team', 'In', ['red']]]];
        $readers = ['readers', 'ContainsAny', ['ann']];
        $or = ['And', [['And', [$readers, ['Or', [['number', 'Eq', 3], ['number', 'Eq', 4]]]]], $flat]];
        $group = ['And', [['And', [$readers, ['And', [['active', 'Eq', true], ['Or', [['number', 'Eq', 3], ['price', 'Gt', 20]]]]]]], $flat]];
        $this->assertSame([$or, $or, $group, $group], array_column($this->queries(), 'filters'));
    }

    #[Test]
    public function it_ands_an_extension_group_holding_a_string_list_with_the_flat_conditions_and_the_client_group(): void
    {
        config(['rest.extensions' => [ReadersExtension::class, CheapEngineRestriction::class, ReaderGroupEngineRestriction::class]]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
        $this->rows(['V-c'], 1);

        $this->search('grouped-reader-vouchers', [])->assertOk();
        $this->search('grouped-reader-vouchers', ['filters' => [['field' => 'number', 'value' => 3], ['field' => 'number', 'value' => 4, 'type' => 'or']]])->assertOk();

        $flat = ['And', [['__soft_deleted', 'Eq', 0], ['price', 'Lt', 35], ['team', 'In', ['red']]]];
        $readers = ['readers', 'ContainsAny', ['ann']];
        $group = ['Or', [['number', 'Eq', 1], ['And', [['price', 'Gt', 20], ['readers', 'ContainsAny', ['bob']]]]]];
        $alone = ['And', [['And', [$readers, $group]], $flat]];
        $beside = ['And', [['And', [$readers, ['And', [$group, ['Or', [['number', 'Eq', 3], ['number', 'Eq', 4]]]]]]], $flat]];
        $this->assertSame([$alone, $alone, $beside, $beside], array_column($this->queries(), 'filters'));
    }
}
