<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\DocumentedStageController;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\ViewerStageController;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

class OperationsTest extends DocumentationTestCase
{
    #[Test]
    public function it_documents_each_route_of_a_declaration_and_no_catch_all_route(): void
    {
        $this->assertSame([
            '/notes',
            '/notes/search',
            '/notes/mutate',
            '/shows',
            '/shows/search',
            '/shows/mutate',
            '/shows/actions/reschedule-shows',
            '/shows/actions/cancel-shows',
            '/shows/actions/import-shows',
            '/shows/actions/archive-shows',
            '/shows/metrics/show-count',
            '/shows/metrics/shows-per-month',
            '/shows/metrics/shows-by-status',
            '/shows/metrics/top-stages',
            '/shows/metrics/seats-goal',
            '/shows/metrics/occupancy',
            '/shows/restore',
            '/shows/force',
            '/shows/actions/{action}/batches/{batch}',
            '/stages',
            '/stages/search',
            '/stages/mutate',
        ], array_keys($this->document()['paths']));
    }

    #[Test]
    public function it_summarizes_each_operation(): void
    {
        $summaries = [];

        foreach ($this->document()['paths'] as $operations) {
            foreach ($operations as $operation) {
                $summaries[$operation['operationId']] = $operation['summary'];
            }
        }

        $this->assertSame([
            'notes.details' => 'Get Note details',
            'notes.destroy' => 'Delete Note',
            'notes.search' => 'Search Note',
            'notes.mutate' => 'Mutate Note',
            'shows.details' => 'Get Show details',
            'shows.destroy' => 'Delete Show',
            'shows.search' => 'Search Show',
            'shows.mutate' => 'Mutate Show',
            'shows.operate.reschedule-shows' => 'Reschedule Shows',
            'shows.operate.cancel-shows' => 'Cancel Shows',
            'shows.operate.import-shows' => 'Import Shows',
            'shows.operate.archive-shows' => 'Archive Shows',
            'shows.metric.show-count' => 'Show Count',
            'shows.metric.shows-per-month' => 'Shows Per Month',
            'shows.metric.shows-by-status' => 'Shows By Status',
            'shows.metric.top-stages' => 'Top Stages',
            'shows.metric.seats-goal' => 'Seats Goal',
            'shows.metric.occupancy' => 'Occupancy',
            'shows.restore' => 'Restore Show',
            'shows.forceDestroy' => 'Force delete Show',
            'shows.batch' => 'Batch progress of Show actions',
            'stages.details' => 'Get Stage details',
            'stages.destroy' => 'Delete Stage',
            'stages.search' => 'Search Stage',
            'stages.mutate' => 'Mutate Stage',
        ], $summaries);
    }

    #[Test]
    public function it_replaces_the_responses_that_scramble_infers_on_the_routes_of_the_package(): void
    {
        $document = $this->document();
        $placeholders = [];

        foreach ($document['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if ($operation['responses'][200] === ['description' => 'Success', 'content' => ['application/json' => ['schema' => ['type' => 'object']]]]) {
                    $placeholders[] = strtoupper($method).' '.$path;
                }
            }
        }

        $this->assertSame([], $placeholders);
        $this->assertArrayNotHasKey('ValidationException', $document['components']['responses'] ?? []);
        $this->assertArrayNotHasKey('AuthenticationException', $document['components']['responses'] ?? []);
    }

    #[Test]
    public function it_keeps_the_responses_that_scramble_infers_on_the_routes_of_the_application(): void
    {
        Route::post('api/tickets', fn (Request $request): array => $request->validate(['title' => ['required', 'string']]))->middleware('auth');

        $document = $this->document();

        $this->assertSame(['$ref' => '#/components/responses/ValidationException'], $document['paths']['/tickets']['post']['responses'][422]);
        $this->assertSame(['$ref' => '#/components/responses/AuthenticationException'], $document['paths']['/tickets']['post']['responses'][401]);
        $this->assertArrayHasKey('ValidationException', $document['components']['responses']);
        $this->assertArrayHasKey('AuthenticationException', $document['components']['responses']);
    }

    #[Test]
    #[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
    public function it_documents_each_resource_as_a_guest_sees_it(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('viewer-stages', ViewerStageController::class));
        Gate::define('viewApiDocs', fn (Authenticatable $user): bool => true);

        $response = $this->actingAs(new User(['id' => 1, 'name' => 'Ada']))->getJson('docs/api.json');

        $response->assertOk();
        $this->assertSame(['code', 'name'], $response->json('components.schemas.ViewerStageSelect.properties.field.enum'));
    }

    #[Test]
    public function it_keeps_the_summary_and_the_body_of_an_endpoint_that_the_controller_overrides_with_a_docblock(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('documented-stages', DocumentedStageController::class));

        $paths = $this->document()['paths'];

        $this->assertSame('Search Stage', $paths['/documented-stages/search']['post']['summary']);
        $this->assertSame('Mutate Stage', $paths['/documented-stages/mutate']['post']['summary']);
        $this->assertSame($paths['/stages/search']['post']['requestBody'], $paths['/documented-stages/search']['post']['requestBody']);
        $this->assertSame($paths['/stages/mutate']['post']['requestBody'], $paths['/documented-stages/mutate']['post']['requestBody']);
    }
}
