<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstNarrowing;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\CappedShowController;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\PagedShowController;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [MaskingFields::class, FirstNarrowing::class])]
class SearchTotalsValidationTest extends SearchTotalsTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function tearDown(): void
    {
        FirstNarrowing::$narrow = null;

        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $totals
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('refusals')]
    public function it_refuses_totals_that_the_resource_does_not_compute(mixed $totals, array $errors): void
    {
        $response = $this->search(['totals' => $totals]);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
    }

    /** @return array<string, array{mixed, array<string, list<string>>}> */
    public static function refusals(): array
    {
        return [
            'no measure or group' => [[], ['search.totals' => ['The search.totals field must ask for measures or groups.']]],
            'empty lists' => [['measures' => [], 'groups' => []], ['search.totals' => ['The search.totals field must ask for measures or groups.']]],
            'not an object' => ['all', ['search.totals' => ['The search.totals field must be an array.']]],
            'an unknown key' => [['groups' => [['field' => 'status']], 'sorts' => []], ['search.totals.sorts' => ['The search.totals.sorts field is prohibited.']]],
            'an undeclared group' => [['groups' => [['field' => 'starts_on']]], ['search.totals.groups.0.field' => ['The selected search.totals.groups.0.field is invalid.']]],
            'a measured field that is not grouped' => [['groups' => [['field' => 'price']]], ['search.totals.groups.0.field' => ['The selected search.totals.groups.0.field is invalid.']]],
            'an undeclared measure' => [['measures' => [['type' => 'sum', 'field' => 'level']]], ['search.totals.measures.0.field' => ['The selected search.totals.measures.0.field is invalid.']]],
            'an unknown measure type' => [['measures' => [['type' => 'median', 'field' => 'seats']]], ['search.totals.measures.0.type' => ['The selected search.totals.measures.0.type is invalid.']]],
            'count as a measure type' => [['measures' => [['type' => 'count', 'field' => 'seats']]], ['search.totals.measures.0.type' => ['The selected search.totals.measures.0.type is invalid.']]],
            'a measure without a field' => [['measures' => [['type' => 'sum']]], ['search.totals.measures.0.field' => ['The search.totals.measures.0.field field is required.']]],
            'a repeated measure' => [['measures' => [['type' => 'sum', 'field' => 'seats'], ['type' => 'max', 'field' => 'seats'], ['type' => 'sum', 'field' => 'seats']]], ['search.totals.measures.2' => ['The search.totals.measures.2 field has a duplicate value.']]],
            'a repeated group' => [['groups' => [['field' => 'status'], ['field' => 'status', 'limit' => 2]]], ['search.totals.groups.1.field' => ['The search.totals.groups.1.field field has a duplicate value.']]],
            'too many groups' => [['groups' => [['field' => 'status'], ['field' => 'level'], ['field' => 'featured'], ['field' => 'seats']]], ['search.totals.groups' => ['The search.totals.groups field must not have more than 3 items.']]],
            'a limit of zero' => [['groups' => [['field' => 'status', 'limit' => 0]]], ['search.totals.groups.0.limit' => ['The search.totals.groups.0.limit field must be at least 1.']]],
            'a limit above the groups size' => [['groups' => [['field' => 'status', 'limit' => 101]]], ['search.totals.groups.0.limit' => ['The search.totals.groups.0.limit field must not be greater than 100.']]],
            'a limit that is not an integer' => [['groups' => [['field' => 'status', 'limit' => 'all']]], ['search.totals.groups.0.limit' => ['The search.totals.groups.0.limit field must be an integer.']]],
        ];
    }

    #[Test]
    public function it_refuses_more_measures_than_the_configured_size(): void
    {
        config()->set('rest.limits.sizes.measures', 2);

        $response = $this->search(['totals' => ['measures' => [['type' => 'sum', 'field' => 'seats'], ['type' => 'min', 'field' => 'seats'], ['type' => 'max', 'field' => 'seats']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals.measures' => ['The search.totals.measures field must not have more than 2 items.']]);
    }

    #[Test]
    public function it_counts_the_totals_entries_in_the_entries_of_the_request(): void
    {
        config()->set('rest.limits.totals.entries', 3);

        $response = $this->search(['filters' => [['field' => 'status', 'value' => 'on_sale'], ['field' => 'seats', 'value' => 100]], 'totals' => ['groups' => [['field' => 'status'], ['field' => 'level']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals.groups' => ['The search.totals.groups field goes over the limit of 3 list entries in one request.']]);
    }

    #[Test]
    public function it_prohibits_totals_on_a_resource_that_declares_none(): void
    {
        $response = $this->search(['totals' => ['groups' => [['field' => 'name']]]], 'stages');

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited.']]);
    }

    #[Test]
    public function it_prohibits_totals_on_a_resource_that_caps_its_count(): void
    {
        Route::prefix('api')->group(static function (): void {
            Rest::resource('capped-shows', CappedShowController::class)->register();
        });

        $response = $this->search(['totals' => ['groups' => [['field' => 'status']]]], 'capped-shows');

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited: this resource caps the count of its rows.']]);
    }

    #[Test]
    public function it_prohibits_totals_when_an_extension_caps_the_count_for_the_user(): void
    {
        FirstNarrowing::$narrow = static fn (LimitsContext $context): mixed => $context->maxTotal(100);

        $response = $this->search(['totals' => ['groups' => [['field' => 'status']]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited: this resource caps the count of its rows.']]);
    }

    #[Test]
    public function it_prohibits_totals_on_a_resource_that_paginates_itself(): void
    {
        Route::prefix('api')->group(static function (): void {
            Rest::resource('paged-shows', PagedShowController::class)->register();
        });

        $response = $this->search(['totals' => ['groups' => [['field' => 'status']]]], 'paged-shows');

        $response->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited: this resource counts its pages itself.']]);
    }

    #[Test]
    public function it_answers_a_hidden_or_unfilterable_field_as_an_unknown_one(): void
    {
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->resource instanceof ShowResource) {
                $context->hide('summary');
                $context->unfilterable('status');
                $context->unfilterable('price');
            }
        };

        $response = $this->search(['totals' => [
            'groups' => [['field' => 'summary'], ['field' => 'status'], ['field' => 'unknown']],
            'measures' => [['type' => 'sum', 'field' => 'price'], ['type' => 'sum', 'field' => 'seats']],
        ]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.totals.measures.0.field' => ['The selected search.totals.measures.0.field is invalid.'],
            'search.totals.groups.0.field' => ['The selected search.totals.groups.0.field is invalid.'],
            'search.totals.groups.1.field' => ['The selected search.totals.groups.1.field is invalid.'],
            'search.totals.groups.2.field' => ['The selected search.totals.groups.2.field is invalid.'],
        ]);
    }

    #[Test]
    public function it_refuses_totals_in_an_include(): void
    {
        $response = $this->search(['totals' => ['groups' => [['field' => 'name']]], 'includes' => [['relation' => 'shows', 'totals' => ['groups' => [['field' => 'status']]]]]], 'stages');

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'search.totals' => ['The search.totals field is prohibited.'],
            'search.includes.0.totals' => ['The search.includes.0.totals field is prohibited.'],
        ]);
    }

    #[Test]
    public function it_refuses_totals_in_the_search_of_an_action_and_of_a_metric(): void
    {
        $action = $this->postJson('/api/shows/actions/reschedule-shows', ['search' => ['totals' => ['groups' => [['field' => 'status']]]]]);
        $metric = $this->postJson('/api/shows/metrics/show-count', ['search' => ['totals' => ['groups' => [['field' => 'status']]]]]);

        $action->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited.']]);
        $metric->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['The search.totals field is prohibited.']]);
    }
}
