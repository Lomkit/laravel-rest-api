<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineOptionsInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListedTeamVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\RecordingTypesenseEngine;
use Lomkit\Rest\Tests\Fixtures\ReceivedFields;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Client;

#[Group('scout')]
#[Group('typesense')]
class TypesenseCuratedHitsTest extends TypesenseTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['rest.extensions' => [EngineOptionsInstructionsExtension::class]]);
        $this->actingAs(new GenericUser(['id' => 1, 'teams' => ['red']]));
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<int>  $ids
     */
    #[Test]
    #[DataProvider('pinnedOutside')]
    public function it_keeps_a_pinned_row_outside_the_perimeter_out_of_the_page_and_the_total(array $options, array $ids): void
    {
        $response = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'instructions' => [self::instruction($options)]]);

        $response->assertOk()->assertJsonPath('total', count($ids));
        $this->assertEqualsCanonicalizing($ids, $response->json('data.*.id'));
    }

    /** @return array<string, array{array<string, mixed>, list<int>}> */
    public static function pinnedOutside(): array
    {
        return [
            'pinned hits from an instruction' => [['pinned_hits' => 'V-d:1'], [1, 3, 5]],
            'pinned hits beside hidden hits' => [['pinned_hits' => 'V-d:1', 'hidden_hits' => 'V-a'], [1, 5]],
        ];
    }

    /** @param list<array<string, mixed>> $items */
    #[Test]
    #[DataProvider('curations')]
    public function it_keeps_a_row_that_a_curation_set_includes_outside_the_perimeter_out_of_the_page_and_the_total(array $items): void
    {
        $set = $this->collection.'_curation';
        $this->beforeApplicationDestroyed(fn () => $this->collections->forget("curation_sets/{$set}"));
        $this->collections->curate($this->collection, $set, $items);

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertJsonPath('total', 3);
        $this->assertEqualsCanonicalizing([1, 3, 5], $response->json('data.*.id'));
    }

    /** @return array<string, array{list<array<string, mixed>>}> */
    public static function curations(): array
    {
        $rule = ['id' => 'report', 'rule' => ['query' => 'report', 'match' => 'exact'], 'includes' => [['id' => 'V-d', 'position' => 1]]];

        return [
            'a rule that includes a row' => [[$rule]],
            'a rule that includes a row and turns off the filter of curated hits' => [[[...$rule, 'filter_curated_hits' => false]]],
        ];
    }

    #[Test]
    public function it_keeps_a_row_that_a_preset_pins_outside_the_perimeter_out_of_the_page_and_the_total(): void
    {
        $preset = $this->collection.'_preset';
        $this->beforeApplicationDestroyed(fn () => $this->collections->forget("presets/{$preset}"));
        $this->collections->preset($preset, ['filter_curated_hits' => false, 'pinned_hits' => 'V-d:1']);

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'instructions' => [self::instruction(['preset' => $preset])]]);

        $response->assertOk()->assertJsonPath('total', 3);
        $this->assertEqualsCanonicalizing([1, 3, 5], $response->json('data.*.id'));
    }

    #[Test]
    public function it_keeps_a_pinned_row_outside_the_perimeter_out_of_an_action_on_the_search(): void
    {
        $received = $this->app->instance(ReceivedFields::class, new ReceivedFields);

        $response = $this->postJson('/api/team-vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report'], 'sorts' => [['field' => 'price', 'direction' => 'asc']], 'instructions' => [self::instruction(['pinned_hits' => 'V-d:1'])]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame([['handle', ['ids' => [1, 3, 5]]]], $received->calls());
    }

    #[Test]
    public function it_keeps_a_pinned_row_outside_the_perimeter_out_of_a_long_search_through_multi_search(): void
    {
        $engine = new RecordingTypesenseEngine(new Client(config('scout.typesense.client-settings')), 1000, config('scout.typesense'));
        $this->useEngine($engine);
        $excluded = array_map(fn (int $index): string => sprintf('X-%04d', $index), range(1, 300));

        $response = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'instructions' => [self::instruction(['pinned_hits' => 'V-d:1'], $excluded)]]);

        $response->assertOk()->assertJsonPath('total', 3);
        $this->assertEqualsCanonicalizing([1, 3, 5], $response->json('data.*.id'));
        $this->assertSame(['multi_search'], $engine->endpoints);
    }

    #[Test]
    public function it_keeps_a_pinned_row_inside_the_perimeter_first(): void
    {
        $byPrice = [['field' => 'price', 'direction' => 'asc']];

        $sorted = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'sorts' => $byPrice]);
        $pinned = $this->search('team-vouchers', ['text' => ['value' => 'report'], 'sorts' => $byPrice, 'instructions' => [self::instruction(['pinned_hits' => 'V-e:1'])]]);

        $sorted->assertOk()->assertJsonPath('data.*.id', [1, 3, 5]);
        $pinned->assertOk()->assertJsonPath('total', 3)->assertJsonPath('data.*.id', [5, 1, 3]);
    }

    #[Test]
    public function it_keeps_a_pinned_row_outside_the_perimeter_out_of_an_engine_list(): void
    {
        $response = $this->search('listed-team-vouchers', ['source' => 'engine', 'instructions' => [self::instruction(['pinned_hits' => 'V-d:1'])]]);

        $response->assertOk()->assertJsonPath('total', 4);
        $this->assertEqualsCanonicalizing([1, 2, 3, 5], $response->json('data.*.id'));
    }

    #[\Override]
    protected function defineRoutes($router): void
    {
        parent::defineRoutes($router);

        Route::prefix('api')->group(fn () => Rest::resource('listed-team-vouchers', ListedTeamVoucherController::class));
    }

    /**
     * @param  array<string, mixed>  $options
     * @param  list<string>  $excluded
     * @return array<string, mixed>
     */
    private static function instruction(array $options, array $excluded = []): array
    {
        return ['name' => 'eo.options', 'fields' => [['name' => 'options', 'value' => $options], ['name' => 'excluded_codes', 'value' => $excluded]]];
    }
}
