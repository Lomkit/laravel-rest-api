<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ContainsVoucherController;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\WithLines;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use Lomkit\Rest\Text\Engine\EngineInstruction;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineInstructionsTest extends EngineSearchTestCase
{
    private ReceivedFields $received;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->received = $this->app->instance(ReceivedFields::class, new ReceivedFields);
        Route::prefix('api')->group(fn () => Rest::resource('contains-vouchers', ContainsVoucherController::class)->register());
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<int>  $ids
     * @param  list<array{string, array<string, mixed>}>  $calls
     */
    #[Test]
    #[DataProvider('applied')]
    public function it_applies_an_instruction_where_the_search_runs(string $driver, array $search, array $ids, int $total, array $calls): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->search('vouchers', $search);

        $response->assertOk()->assertExactJson($this->page($ids, $total));
        $this->assertSame($calls, $this->received->calls());
    }

    /** @return iterable<string, array{string, array<string, mixed>, list<int>, int, list<array{string, array<string, mixed>}>}> */
    public static function applied(): iterable
    {
        $pricedUpTo = ['instructions' => [['name' => 'priced-up-to', 'fields' => [['name' => 'max', 'value' => 7.25]]]]];
        $numberedFrom = ['instructions' => [['name' => 'numbered-from', 'fields' => [['name' => 'min', 'value' => 3]]]]];

        foreach (self::drivers() as $engine => [$driver]) {
            yield "an instruction on both grammars in a text search on {$engine}" => [$driver, ['text' => ['value' => 'report'], ...$pricedUpTo], [1], 1, [['handleEngine', ['max' => 7.25]]]];
            yield "an instruction on both grammars without text on {$engine}" => [$driver, $pricedUpTo, [3, 1], 2, [['handle', ['max' => 7.25]]]];
            yield "an engine instruction in a text search on {$engine}" => [$driver, ['text' => ['value' => 'report'], ...$numberedFrom], [5, 1], 3, [['handleEngine', ['min' => 3]]]];
            yield "an engine instruction with its default value on {$engine}" => [$driver, ['text' => ['value' => 'report'], 'instructions' => [['name' => 'numbered-from']]], [5, 1, 3], 4, [['handleEngine', ['min' => 1]]]];
            yield "a database instruction without text on {$engine}" => [$driver, ['instructions' => [['name' => 'with-lines']]], [5, 3, 1], 3, []];
        }
    }

    /** @param array<string, mixed> $payload */
    #[Test]
    #[DataProvider('refused')]
    public function it_refuses_an_instruction_that_does_not_apply_where_the_search_runs(string $uri, array $payload, string $key): void
    {
        $response = $this->postJson($uri, $payload);

        $response->assertUnprocessable()->assertExactJson([
            'message' => "The selected {$key} is invalid.",
            'errors' => [$key => ["The selected {$key} is invalid."]],
        ]);
        $this->assertSame([], $this->received->calls());
    }

    /** @return array<string, array{string, array<string, mixed>, string}> */
    public static function refused(): array
    {
        $engineOnly = [['name' => 'numbered-from']];

        return [
            'an engine instruction without text' => ['/api/vouchers/search', ['search' => ['instructions' => $engineOnly]], 'search.instructions.0.name'],
            'an engine instruction with a blank text' => ['/api/vouchers/search', ['search' => ['text' => ['value' => ' '], 'instructions' => $engineOnly]], 'search.instructions.0.name'],
            'an engine instruction in an include' => ['/api/voucher-lines/search', ['search' => ['includes' => [['relation' => 'voucher', 'instructions' => $engineOnly]]]], 'search.includes.0.instructions.0.name'],
            'an engine instruction in an action without text' => ['/api/vouchers/actions/deactivate-vouchers', ['search' => ['instructions' => $engineOnly]], 'search.instructions.0.name'],
            'an engine instruction on a database text search' => ['/api/contains-vouchers/search', ['search' => ['text' => ['value' => 'report'], 'instructions' => $engineOnly]], 'search.instructions.0.name'],
            'a database instruction in a text search' => ['/api/vouchers/search', ['search' => ['text' => ['value' => 'report'], 'instructions' => [['name' => 'with-lines']]]], 'search.instructions.0.name'],
        ];
    }

    #[Test]
    public function it_applies_an_engine_instruction_to_the_text_search_of_an_action(): void
    {
        $response = $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => [
            'text' => ['value' => 'report'],
            'instructions' => [['name' => 'numbered-from', 'fields' => [['name' => 'min', 'value' => 3]]]],
        ]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2]]);
        $this->assertSame([['handleEngine', ['min' => 3]], ['handle', ['ids' => [5, 1]]]], $this->received->calls());
    }

    #[Test]
    public function it_refuses_to_apply_a_database_instruction_to_an_engine_query(): void
    {
        $resource = $this->resource(VoucherResource::class, $this->context(['search' => ['text' => ['value' => 'report']]]));
        $text = $resource->context()->text($resource);
        $query = $text->engineQuery($resource->context()->request);
        $this->assertNotNull($query);

        $this->expectExceptionObject(InvalidInstructionException::queryOnly(WithLines::class));

        (new EngineInstruction($resource, 'with-lines'))->apply($query);
    }

    /** @param list<bool> $flags */
    #[Test]
    #[DataProvider('details')]
    public function it_describes_the_instructions_that_apply_to_a_text_search(string $resource, array $flags): void
    {
        $response = $this->getJson("/api/{$resource}");

        $response->assertOk()
            ->assertJsonPath('data.instructions.*.uriKey', ['priced-up-to', 'numbered-from', 'with-lines'])
            ->assertJsonPath('data.instructions.*.text', $flags);
    }

    /** @return array<string, array{string, list<bool>}> */
    public static function details(): array
    {
        return [
            'a text search on an engine' => ['vouchers', [true, true, false]],
            'a text search on the database' => ['contains-vouchers', [true, false, true]],
        ];
    }
}
