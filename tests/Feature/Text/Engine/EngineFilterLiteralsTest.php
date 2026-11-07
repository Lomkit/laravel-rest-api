<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\NullEngine;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTurbopufferEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\LiteralVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineFilterLiteralsTest extends EngineSearchTestCase
{
    private const array ENGINES = ['collection', 'database', 'null', 'meilisearch', 'typesense', 'algolia', 'turbopuffer'];

    private const array VALUES = [
        'a backtick' => ['x`) || (team:=blue', 'search.filters.0.value'],
        'a backtick in a list' => [['a', 'b`] || team:!=[red'], 'search.filters.0.value.1'],
        'a colon before a parenthesis' => ['x: (1, 2)', 'search.filters.0.value'],
        'a colon before a bracket and a parenthesis' => ['x: [ (1)] || team:=blue', 'search.filters.0.value'],
        'a trailing star' => ['Report* ', 'search.filters.0.value'],
        'a trailing star in a list' => [['a', 'Report*'], 'search.filters.0.value.1'],
        'double quotes around the value' => [' "Report alpha" ', 'search.filters.0.value'],
        'a number with a plus sign' => ['+1', 'search.filters.0.value'],
        'a number with a plus sign in its exponent' => [['a', '1e+5'], 'search.filters.0.value.1'],
    ];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('literal-vouchers', LiteralVoucherController::class)->register());
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
    }

    #[Test]
    #[DataProvider('unquotable')]
    public function it_refuses_a_filter_value_the_search_engines_cannot_match_as_it_is(string $engine, mixed $value, string $path): void
    {
        $this->useEngine(self::engine($engine));

        $response = $this->search('literal-vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'title', 'operator' => is_array($value) ? 'in' : '=', 'value' => $value]]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => "The {$path} field holds characters the search engine cannot match as they are.",
            'errors' => [$path => ["The {$path} field holds characters the search engine cannot match as they are."]],
        ]);
    }

    /** @return array<string, array{string, mixed, string}> */
    public static function unquotable(): array
    {
        $cases = [];

        foreach (self::ENGINES as $engine) {
            foreach (self::VALUES as $name => [$value, $path]) {
                $cases["{$engine}: {$name}"] = [$engine, $value, $path];
            }
        }

        return $cases;
    }

    #[Test]
    #[DataProvider('engines')]
    public function it_refuses_a_perimeter_value_the_search_engines_cannot_match_as_it_is(string $engine): void
    {
        $this->useEngine(self::engine($engine));
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red', 're`d']]));
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineQueryException::nonLiteralValue('team'));

        $this->search('literal-vouchers', ['text' => ['value' => 'report']]);
    }

    /** @return array<string, array{string}> */
    public static function engines(): array
    {
        return array_combine(self::ENGINES, array_map(fn (string $engine): array => [$engine], self::ENGINES));
    }

    private static function engine(string $name): object
    {
        $settings = new SettingsClient(fn (): array => []);

        return match ($name) {
            'collection' => new CollectionEngine,
            'database' => new DatabaseEngine,
            'null' => new NullEngine,
            'meilisearch' => new FakeMeilisearchEngine($settings),
            'typesense' => new FakeTypesenseEngine,
            'algolia' => new FakeAlgolia4Engine($settings),
            default => new FakeTurbopufferEngine,
        };
    }
}
