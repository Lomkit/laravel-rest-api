<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Support\Facades\Route;
use Laravel\Scout\Engines\CollectionEngine;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\NamePrefixesRestriction;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingAlgolia4Engine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListCapturingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListPerimeterPrefixedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\NamePrefixes;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PerimeterPrefixedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PrefixedVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PrefixedVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ResourceIndexListSearchTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(function (): void {
            Rest::resource('prefixed-vouchers', PrefixedVoucherController::class)->register();
            Rest::resource('perimeter-prefixed-vouchers', PerimeterPrefixedVoucherController::class)->register();
            Rest::resource('list-perimeter-prefixed-vouchers', ListPerimeterPrefixedVoucherController::class)->register();
        });
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
    }

    #[Test]
    #[DataProvider('typesenseFilters')]
    public function it_sends_the_list_of_an_instruction_to_typesense(string $resource, string $instruction, string $filter): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);

        $this->search($resource, ['text' => ['value' => 'report'], 'instructions' => [['name' => $instruction]]])->assertOk();

        $this->assertSame([$filter], $engine->filters);
    }

    /** @return array<string, array{string, string, string}> */
    public static function typesenseFilters(): array
    {
        return [
            'without perimeter' => ['prefixed-vouchers', 'name-prefixes', 'name_prefixes:=[`re`, `rep`]'],
            'beside a perimeter' => ['perimeter-prefixed-vouchers', 'name-prefixes', 'team:=`red` && name_prefixes:=[`re`, `rep`]'],
            'a list that the perimeter list narrows' => ['list-perimeter-prefixed-vouchers', 'name-prefixes', 'name_prefixes:=[`re`]'],
            'a list anded with the perimeter list' => ['list-perimeter-prefixed-vouchers', 'other-name-prefixes', '(name_prefixes:=[`re`]) && (name_prefixes:=[`bu`])'],
        ];
    }

    #[Test]
    #[DataProvider('meilisearchFilters')]
    public function it_sends_the_list_of_an_instruction_to_meilisearch(string $resource, string $instruction, string $filter): void
    {
        $engine = new ListCapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));
        $this->useEngine($engine);

        $this->search($resource, ['text' => ['value' => 'report'], 'instructions' => [['name' => $instruction]]])->assertOk();

        $this->assertSame([$filter], $engine->filters);
    }

    /** @return array<string, array{string, string, string}> */
    public static function meilisearchFilters(): array
    {
        return [
            'without perimeter' => ['prefixed-vouchers', 'name-prefixes', 'name_prefixes IN ["re", "rep"]'],
            'beside a perimeter' => ['perimeter-prefixed-vouchers', 'name-prefixes', 'team="red" AND name_prefixes IN ["re", "rep"]'],
            'a list that the perimeter list narrows' => ['list-perimeter-prefixed-vouchers', 'name-prefixes', 'name_prefixes IN ["re"]'],
            'a list anded with the perimeter list' => ['list-perimeter-prefixed-vouchers', 'other-name-prefixes', '(name_prefixes IN ["re"]) AND (name_prefixes IN ["bu"])'],
        ];
    }

    #[Test]
    public function it_answers_422_to_an_instruction_list_that_the_perimeter_list_conflicts_with_on_algolia(): void
    {
        $this->useEngine(new FilterCapturingAlgolia4Engine(new SettingsClient(fn (): array => [])));

        $response = $this->search('list-perimeter-prefixed-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'other-name-prefixes']]]);

        $message = 'The search.instructions.0 field restricts a list with values that neither hold nor are held by the list that the search already keeps, and the search engine cannot require both lists.';
        $response->assertStatus(422)->assertExactJson(['message' => $message, 'errors' => ['search.instructions.0' => [$message]]]);
    }

    #[Test]
    public function it_sends_the_list_of_an_instruction_to_algolia(): void
    {
        $engine = new FilterCapturingAlgolia4Engine(new SettingsClient(fn (): array => []));
        $this->useEngine($engine);

        $this->search('prefixed-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'name-prefixes']]])->assertOk();

        $this->assertSame(["(name_prefixes:'re' OR name_prefixes:'rep')"], $engine->filters);
    }

    #[Test]
    #[DataProvider('clientRequests')]
    public function it_answers_a_client_that_names_the_list_as_it_answers_an_unknown_name(string $key): void
    {
        $this->useEngine(new FilterCapturingTypesenseEngine);
        $request = fn (string $name): array => match ($key) {
            'filters' => ['text' => ['value' => 'report'], 'filters' => [['field' => $name, 'operator' => 'in', 'value' => ['re']]]],
            default => ['text' => ['value' => 'report'], 'sorts' => [['field' => $name, 'direction' => 'asc']]],
        };

        $listed = $this->search('prefixed-vouchers', $request('name_prefixes'));
        $unknown = $this->search('prefixed-vouchers', $request('unknown_prefixes'));

        $listed->assertStatus(422);
        $this->assertSame(
            str_replace('unknown_prefixes', 'name_prefixes', (string) $unknown->assertStatus(422)->getContent()),
            (string) $listed->getContent(),
        );
    }

    /** @return array<string, array{string}> */
    public static function clientRequests(): array
    {
        return [
            'a filter' => ['filters'],
            'a sort' => ['sorts'],
        ];
    }

    #[Test]
    public function it_sends_the_list_of_an_instruction_in_an_engine_list(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);

        $this->search('prefixed-vouchers', ['source' => 'engine', 'instructions' => [['name' => 'name-prefixes']]])->assertOk();

        $this->assertSame(['name_prefixes:=[`re`, `rep`]'], $engine->filters);
    }

    #[Test]
    public function it_sends_the_list_of_an_extension_restriction(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [NamePrefixesRestriction::class]]);

        $this->search('prefixed-vouchers', ['text' => ['value' => 'report']])->assertOk();
        $this->search('prefixed-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'name-prefixes']]])->assertOk();

        $this->assertSame(['name_prefixes:=[`re`, `bu`]', '(name_prefixes:=[`re`, `bu`]) && (name_prefixes:=[`re`, `rep`])'], $engine->filters);
    }

    #[Test]
    public function it_names_the_instruction_that_compares_a_list_on_an_engine_without_lists(): void
    {
        config(['scout.driver' => 'collection']);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineQueryException::unmatchedStringList(new NamePrefixes, PrefixedVoucherResource::class, 'name_prefixes', CollectionEngine::class));

        $this->search('prefixed-vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'name-prefixes']]]);
    }

    #[Test]
    public function it_leaves_the_list_out_of_the_details(): void
    {
        config(['scout.driver' => 'collection']);

        $response = $this->getJson('/api/prefixed-vouchers?with=text');

        $response->assertOk()->assertJsonPath('data.text.engine.filters.*.name', ['number', 'price', 'active', 'code']);
        $this->assertStringNotContainsString('name_prefixes', (string) $response->getContent());
    }
}
