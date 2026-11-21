<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Scout\Engines\DatabaseEngine;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\InstructedLiteralVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\InstructedLiteralVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineInstructionValuesTest extends EngineSearchTestCase
{
    private const string PHONE = '+33612345678';

    private const string CHARACTERS = 'The search.instructions.0 field holds characters the search engine cannot match as they are.';

    private const string VALUE = 'The search.instructions.0 field holds a value the search engine cannot compare.';

    private const array SEARCH = ['text' => ['value' => 'report'], 'instructions' => [['name' => 'code-among', 'fields' => [['name' => 'code', 'value' => self::PHONE]]]]];

    private FilterCapturingTypesenseEngine $engine;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('instructed-literal-vouchers', InstructedLiteralVoucherController::class)->register());
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
        $this->engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($this->engine);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
    }

    #[Test]
    public function it_answers_422_to_an_instruction_value_the_search_engine_cannot_match_as_it_is_without_searching(): void
    {
        $response = $this->search('instructed-literal-vouchers', self::SEARCH);

        $response->assertUnprocessable()->assertExactJson(['message' => self::CHARACTERS, 'errors' => ['search.instructions.0' => [self::CHARACTERS]]]);
        $this->assertSame([], $this->engine->filters);
    }

    #[Test]
    public function it_answers_422_to_an_instruction_value_on_the_database_driver_without_reading_a_row(): void
    {
        $this->useEngine(new DatabaseEngine);
        DB::enableQueryLog();

        $response = $this->search('instructed-literal-vouchers', self::SEARCH);

        $response->assertUnprocessable()->assertExactJson(['message' => self::CHARACTERS, 'errors' => ['search.instructions.0' => [self::CHARACTERS]]]);
        $this->assertSame([], DB::getQueryLog());
    }

    #[Test]
    public function it_names_the_instruction_that_passes_the_refused_value_after_a_valid_one(): void
    {
        $message = 'The search.instructions.1 field holds characters the search engine cannot match as they are.';

        $response = $this->search('instructed-literal-vouchers', ['text' => ['value' => 'report'], 'instructions' => [
            ['name' => 'code-equals', 'fields' => [['name' => 'code', 'value' => 'V-a']]],
            ['name' => 'code-among', 'fields' => [['name' => 'code', 'value' => self::PHONE]]],
        ]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.instructions.1' => [$message]]]);
        $this->assertSame([], $this->engine->filters);
    }

    #[Test]
    public function it_answers_422_to_an_instruction_value_the_attribute_cannot_take(): void
    {
        $response = $this->search('instructed-literal-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'code-equals']]]);

        $response->assertUnprocessable()->assertExactJson(['message' => self::VALUE, 'errors' => ['search.instructions.0' => [self::VALUE]]]);
        $this->assertSame([], $this->engine->filters);
    }

    #[Test]
    #[DataProvider('runs')]
    public function it_refuses_an_instruction_value_of_a_programmatic_search_with_a_validation_exception(string $run): void
    {
        $search = Rest::search(InstructedLiteralVoucherResource::class)
            ->as(new GenericUser(['id' => 1, 'teams' => ['red']]))
            ->payload(self::SEARCH);

        try {
            $search->{$run}();
            $this->fail("{$run}() returned instead of refusing the instruction value.");
        } catch (ValidationException $refusal) {
            $this->assertSame(['search.instructions.0' => [self::CHARACTERS]], $refusal->errors());
        }

        $this->assertSame([], $this->engine->filters);
    }

    /** @return array<string, array{string}> */
    public static function runs(): array
    {
        return [
            'page' => ['page'],
            'render' => ['render'],
            'lazy' => ['lazy'],
        ];
    }

    #[Test]
    public function it_answers_422_to_an_action_on_a_search_whose_instruction_value_the_search_engine_cannot_match(): void
    {
        $response = $this->postJson('/api/instructed-literal-vouchers/actions/deactivate-vouchers', ['search' => self::SEARCH]);

        $response->assertUnprocessable()->assertExactJson(['message' => self::CHARACTERS, 'errors' => ['search.instructions.0' => [self::CHARACTERS]]]);
        $this->assertSame([], $this->engine->filters);
        $this->assertSame([5], Voucher::query()->withTrashed()->where('active', false)->pluck('id')->all());
    }

    #[Test]
    public function it_still_throws_for_the_same_value_in_the_perimeter(): void
    {
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => [self::PHONE]]));
        $this->withoutExceptionHandling();

        $this->expectException(InvalidEngineQueryException::class);
        $this->expectExceptionMessage('The search engine cannot match this value of the attribute [team] as it is.');

        $this->search('instructed-literal-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'code-among', 'fields' => [['name' => 'code', 'value' => 'V-a']]]]]);
    }

    #[Test]
    public function it_still_throws_for_an_undeclared_attribute_inside_an_instruction(): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineQueryException::undeclaredAttribute('price'));

        $this->search('instructed-literal-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'priced-up-to', 'fields' => [['name' => 'max', 'value' => 10]]]]]);
    }
}
