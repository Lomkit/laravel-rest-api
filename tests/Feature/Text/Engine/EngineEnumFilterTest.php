<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\KindFieldVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\KindVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingCollectionEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineEnumFilterTest extends EngineSearchTestCase
{
    private const string INVALID = 'The selected %s is invalid.';

    private RecordingCollectionEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->engine = new RecordingCollectionEngine;
        $this->useEngine($this->engine);
        Route::prefix('api')->group(function (): void {
            Rest::resource('kind-vouchers', KindVoucherController::class)->register();
            Rest::resource('kind-field-vouchers', KindFieldVoucherController::class)->register();
        });
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('unknownValues')]
    public function it_refuses_a_value_outside_the_enum_before_searching(string $resource, array $search, string $key): void
    {
        $response = $this->search($resource, $search);

        $message = sprintf(self::INVALID, $key);
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => [$key => [$message]]]);
        $this->assertSame([], $this->engine->searches);
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function unknownValues(): array
    {
        $resources = ['kind-vouchers' => 'an Enum in filterable()', 'kind-field-vouchers' => 'a field of the resource'];
        $sources = ['a text search' => ['text' => ['value' => 'report']], 'an engine list' => ['source' => 'engine']];
        $filters = [
            '=' => [['field' => 'kind', 'value' => 'refund'], 'search.filters.0.value'],
            '!=' => [['field' => 'kind', 'operator' => '!=', 'value' => 'refund'], 'search.filters.0.value'],
            'in' => [['field' => 'kind', 'operator' => 'in', 'value' => ['credit', 'refund']], 'search.filters.0.value.1'],
            'not in' => [['field' => 'kind', 'operator' => 'not in', 'value' => ['refund', 'debit']], 'search.filters.0.value.0'],
        ];
        $cases = [];

        foreach ($resources as $resource => $declared) {
            foreach ($sources as $source => $search) {
                foreach ($filters as $operator => [$filter, $key]) {
                    $cases["{$source} with {$operator} on {$declared}"] = [$resource, [...$search, 'filters' => [$filter]], $key];
                }
            }
        }

        return $cases;
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('knownValues')]
    public function it_sends_a_value_of_the_enum_to_the_engine(string $resource, array $search): void
    {
        $engine = new RecordingCollectionEngine(runs: false);
        $this->useEngine($engine);

        $response = $this->search($resource, [...$search, 'filters' => [['field' => 'kind', 'operator' => 'in', 'value' => ['credit', 'debit']]]]);

        $response->assertOk()->assertExactJson($this->page([], 0));
        $this->assertCount(1, $engine->searches);
        $this->assertSame(['kind' => ['credit', 'debit']], $engine->searches[0]->whereIns);
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function knownValues(): array
    {
        return [
            'a text search on an Enum in filterable()' => ['kind-vouchers', ['text' => ['value' => 'report']]],
            'an engine list on an Enum in filterable()' => ['kind-vouchers', ['source' => 'engine']],
            'a text search on a field of the resource' => ['kind-field-vouchers', ['text' => ['value' => 'report']]],
            'an engine list on a field of the resource' => ['kind-field-vouchers', ['source' => 'engine']],
        ];
    }
}
