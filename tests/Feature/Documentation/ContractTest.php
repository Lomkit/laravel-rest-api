<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\CappedStageController;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\UngatedLabelResource;
use Lomkit\Rest\Tests\Feature\Documentation\Stubs\UngatedNoteResource;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Festival\NoteResource;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\Stage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ContractTest extends DocumentationTestCase
{
    private const array SEARCHES = [
        '/notes/search' => ['includes' => [['relation' => 'notable']], 'gates' => ['view', 'create']],
        '/shows/search' => [
            'includes' => [['relation' => 'stage'], ['relation' => 'artists'], ['relation' => 'comments'], ['relation' => 'labels']],
            'aggregates' => [['relation' => 'comments', 'type' => 'count']],
            'gates' => ['view', 'create', 'feature'],
        ],
        '/stages/search' => ['includes' => [['relation' => 'shows'], ['relation' => 'comments']], 'gates' => ['view']],
    ];

    #[Test]
    public function it_answers_each_details_operation_as_the_document_describes(): void
    {
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($document['paths'] as $path => $operations) {
            if (! str_ends_with($operations['get']['operationId'] ?? '', '.details')) {
                continue;
            }

            $response = $this->getJson('api'.$path);

            $response->assertOk();
            $this->assertSame([], $validator->responseErrors($document, $path, 'get', 200, (string) $response->getContent()), $path);
            $checked[] = $path;
        }

        $this->assertSame(['/notes', '/shows', '/stages'], $checked);
    }

    #[Test]
    public function it_answers_each_search_operation_as_the_document_describes(): void
    {
        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($document['paths'] as $path => $operations) {
            if (! str_ends_with($operations['post']['operationId'] ?? '', '.search')) {
                continue;
            }

            $response = $this->postJson('api'.$path, ['search' => self::SEARCHES[$path]]);

            $response->assertOk();
            $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()), $path);
            $checked[] = $path;
        }

        $this->assertSame(['/notes/search', '/shows/search', '/stages/search'], $checked);
    }

    #[Test]
    public function it_answers_a_search_whose_includes_have_more_rows_as_the_document_describes(): void
    {
        $this->seedFestival();
        DB::table('comments')->insert(array_fill(0, 10, ['show_id' => 1, 'rating' => 4, 'body' => 'Again']));
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();

        $response = $this->postJson('api/stages/search', ['search' => ['includes' => [['relation' => 'shows'], ['relation' => 'comments'], ['relation' => 'comments', 'alias' => 'reviews']]]]);

        $response->assertOk();
        $this->assertSame([['comments', 'reviews'], []], array_column($response->json('data'), '@more'));
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, '/stages/search', 'post', 200, (string) $response->getContent()));
        $this->assertSame(['reviews'], array_values(array_diff(array_keys($response->json('data.0')), array_keys($document['components']['schemas']['Stage']['properties']))));
    }

    #[Test]
    public function it_answers_a_search_of_a_resource_with_a_maximum_total_as_the_document_describes(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('capped-stages', CappedStageController::class));
        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();

        $response = $this->postJson('api/capped-stages/search', ['search' => ['includes' => [['relation' => 'shows']]]]);

        $response->assertOk();
        $this->assertFalse($response->json('total_capped'));
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, '/capped-stages/search', 'post', 200, (string) $response->getContent()));
        $this->assertSame(array_keys($document['components']['schemas']['CappedStagePaginated']['properties']), array_keys($response->json()));
    }

    #[Test]
    public function it_sends_each_request_example_of_the_document(): void
    {
        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($document['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
                if (str_ends_with($operation['operationId'], '.mutate') || str_contains($operation['operationId'], '.operate.')) {
                    continue;
                }

                foreach ($operation['requestBody']['content']['application/json']['examples'] ?? [] as $name => $example) {
                    $response = $this->json($method, 'api'.$path, $example['value']);

                    $response->assertOk();
                    $this->assertSame([], $validator->responseErrors($document, $path, $method, 200, (string) $response->getContent()), $path);
                    $checked[] = strtoupper($method).' '.$path.' '.$name;
                }
            }
        }

        $this->assertSame(['DELETE /notes default', 'POST /notes/search default', 'DELETE /shows default', 'POST /shows/search default', 'POST /shows/restore default', 'DELETE /shows/force default', 'DELETE /stages default', 'POST /stages/search default'], $checked);
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  array<class-string, class-string>  $bindings
     */
    #[Test]
    #[DataProvider('acceptedSearches')]
    public function it_answers_a_search_that_the_document_describes(string $path, array $search, array $bindings = []): void
    {
        foreach ($bindings as $abstract => $concrete) {
            $this->app->bind($abstract, $concrete);
        }

        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;

        $this->assertSame([], $validator->requestErrors($document, $path, 'post', ['search' => $search]));

        $response = $this->postJson('api'.$path, ['search' => $search]);

        $response->assertOk();
        $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()));
    }

    /** @return array<string, array{0: string, 1: array<string, mixed>, 2?: array<class-string, class-string>}> */
    public static function acceptedSearches(): array
    {
        return [
            'a field with its default operator' => ['/shows/search', ['filters' => [['field' => 'title', 'value' => 'Opening night']]]],
            'a pattern' => ['/shows/search', ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => 'Open%']]]],
            'a list of values' => ['/shows/search', ['filters' => [['field' => 'seats', 'operator' => 'in', 'value' => [0, 300]]]]],
            'a range' => ['/shows/search', ['filters' => [['field' => 'starts_on', 'operator' => 'between', 'value' => ['2026-07-01', '2026-07-03']]]]],
            'a null check' => ['/shows/search', ['filters' => [['field' => 'summary', 'operator' => 'is null']]]],
            'a pivot field' => ['/shows/search', ['filters' => [['field' => 'artists.pivot.role', 'value' => 'lead']]]],
            'a field of a related resource' => ['/shows/search', ['filters' => [['field' => 'stage.name', 'value' => 'Main stage']]]],
            'related rows' => ['/shows/search', ['filters' => [['relation' => 'comments', 'exists' => false, 'nested' => [['field' => 'rating', 'operator' => '>=', 'value' => 4]]]]]],
            'related rows of one type' => ['/notes/search', ['filters' => [['relation' => 'notable', 'types' => [Stage::class => [['field' => 'code', 'value' => 'main']]]]]]],
            'a group joined with or' => ['/shows/search', ['filters' => [['field' => 'featured', 'value' => true], ['type' => 'or', 'nested' => [['field' => 'seats', 'operator' => '>', 'value' => 100]]]]]],
            'a string key' => ['/stages/search', ['filters' => [['field' => 'code', 'operator' => 'in', 'value' => ['main', 'side']]]]],
            'sorts on a field and on a field of a relation' => ['/shows/search', ['sorts' => [['field' => 'stage.name', 'direction' => 'desc'], ['field' => 'title']]]],
            'a sort on the key of an aggregate' => ['/shows/search', ['aggregates' => [['relation' => 'comments', 'type' => 'count']], 'sorts' => [['field' => 'comments_count', 'direction' => 'desc']]]],
            'selects, gates, a limit and a page' => ['/shows/search', ['selects' => [['field' => 'title']], 'gates' => ['view', 'create'], 'limit' => 25, 'page' => 2]],
            'a text and the trashed rows' => ['/shows/search', ['text' => ['value' => 'night', 'mode' => 'phrase'], 'trashed' => 'with']],
            'includes of each family' => ['/shows/search', ['includes' => [
                ['relation' => 'stage', 'selects' => [['field' => 'name']]],
                ['relation' => 'comments', 'alias' => 'top_comments', 'limit' => 10, 'sorts' => [['field' => 'rating', 'direction' => 'desc']], 'gates' => ['view']],
                ['relation' => 'artists', 'filters' => [['field' => 'name', 'value' => 'Ada']]],
            ]]],
            'an include of each type' => ['/notes/search', ['includes' => [['relation' => 'notable', 'types' => [Show::class => ['selects' => [['field' => 'title']]], Stage::class => ['includes' => [['relation' => 'shows']]]]]]]],
            'aggregates of each kind' => ['/shows/search', ['aggregates' => [
                ['relation' => 'comments', 'type' => 'count'],
                ['relation' => 'comments', 'type' => 'max', 'field' => 'rating', 'alias' => 'best_rating'],
                ['relation' => 'comments', 'type' => 'avg', 'field' => 'rating', 'filters' => [['field' => 'rating', 'operator' => '>', 'value' => 1]]],
            ]]],
            'a scope with its parameters' => ['/shows/search', ['scopes' => [['name' => 'startingFrom', 'parameters' => ['2026-07-01']]]]],
            'an instruction with its fields' => ['/shows/search', ['instructions' => [['name' => 'only-featured', 'fields' => [['name' => 'featured', 'value' => true], ['name' => 'min_seats', 'value' => null]]]]]],
            'the gates of a resource that turns its gates off' => ['/notes/search', ['gates' => ['view']], [NoteResource::class => UngatedNoteResource::class]],
        ];
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('refusedSearches')]
    public function it_refuses_a_search_that_the_document_does_not_describe(string $path, array $search): void
    {
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));

        $this->assertNotSame([], (new OpenApiValidator)->requestErrors($this->document(), $path, 'post', ['search' => $search]));
        $this->postJson('api'.$path, ['search' => $search])->assertUnprocessable();
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function refusedSearches(): array
    {
        return [
            'a list for an equality' => ['/shows/search', ['filters' => [['field' => 'seats', 'value' => [1]]]]],
            'a value for a null check' => ['/shows/search', ['filters' => [['field' => 'summary', 'operator' => 'is null', 'value' => 'a']]]],
            'a pattern over the length' => ['/shows/search', ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => str_repeat('a', 256)]]]],
            'an operator of another type' => ['/shows/search', ['filters' => [['field' => 'featured', 'operator' => '>', 'value' => true]]]],
            'one value for a range' => ['/shows/search', ['filters' => [['field' => 'seats', 'operator' => 'between', 'value' => [1]]]]],
            'a field that cannot be filtered' => ['/shows/search', ['filters' => [['field' => 'settings', 'value' => 'a']]]],
            'nested filters on related rows of two types' => ['/notes/search', ['filters' => [['relation' => 'notable', 'nested' => [['field' => 'id', 'value' => 1]]]]]],
            'a limit outside the page sizes' => ['/shows/search', ['limit' => 7]],
            'a limit on an include of a relation to one' => ['/shows/search', ['includes' => [['relation' => 'stage', 'limit' => 10]]]],
            'the create gate on an include' => ['/shows/search', ['includes' => [['relation' => 'comments', 'gates' => ['create']]]]],
            'a field that an average does not take' => ['/shows/search', ['aggregates' => [['relation' => 'comments', 'type' => 'avg', 'field' => 'body']]]],
            'a scope without its first parameter' => ['/shows/search', ['scopes' => [['name' => 'startingFrom']]]],
            'a text on a resource without a text search' => ['/stages/search', ['text' => ['value' => 'main']]],
            'an unknown key in a filter' => ['/shows/search', ['filters' => [['field' => 'title', 'value' => 'Opening night', 'note' => 'a']]]],
            'an unknown key in an include' => ['/shows/search', ['includes' => [['relation' => 'comments', 'note' => 'a']]]],
        ];
    }

    #[Test]
    public function it_answers_the_gates_of_an_include_whose_resource_turns_its_gates_off(): void
    {
        $this->app->bind(LabelResource::class, UngatedLabelResource::class);
        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $search = ['includes' => [['relation' => 'labels', 'gates' => ['view']]]];

        $this->assertSame(['/search/includes/0/relation', '/search/includes/0'], array_keys((new OpenApiValidator)->requestErrors($this->document(), '/shows/search', 'post', ['search' => $search])));
        $this->postJson('api/shows/search', ['search' => $search])->assertOk();
    }

    #[Test]
    public function it_documents_mutation_examples_that_the_request_schema_accepts(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($this->mutationExamples($document) as $path => $example) {
            $this->assertSame([], $validator->requestErrors($document, $path, 'post', $example), $path);
            $checked[] = $path;
        }

        $this->assertSame(['/notes/mutate', '/shows/mutate', '/stages/mutate'], $checked);
    }

    #[Test]
    public function it_sends_each_mutation_example_built_from_the_examples_of_its_fields(): void
    {
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;
        $sent = [];

        foreach ($this->mutationExamples($document) as $path => $example) {
            if (! $this->declared($document, $path, $example)) {
                continue;
            }

            $response = $this->postJson('api'.$path, $example);

            $response->assertOk();
            $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()), $path);
            $sent[] = $path;
        }

        $this->assertSame(['/stages/mutate'], $sent);
    }

    /**
     * @param  list<array<string, mixed>>  $mutate
     * @param  array<string, mixed>  $records
     */
    #[Test]
    #[DataProvider('acceptedMutations')]
    public function it_answers_a_mutation_that_the_document_describes(string $path, array $mutate, array $records = []): void
    {
        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;
        $body = ['mutate' => $mutate, ...($records === [] ? [] : ['response' => $records])];

        $this->assertSame([], $validator->requestErrors($document, $path, 'post', $body));

        $response = $this->postJson('api'.$path, $body);

        $response->assertOk();
        $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()));
    }

    /** @return array<string, array{0: string, 1: list<array<string, mixed>>, 2?: array<string, mixed>}> */
    public static function acceptedMutations(): array
    {
        return [
            'a created row' => ['/stages/mutate', [['operation' => 'create', 'attributes' => ['code' => 'jazz', 'name' => 'Jazz tent']]]],
            'an updated row with a null value' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Late show', 'summary' => null]]]],
            'a string key' => ['/stages/mutate', [['operation' => 'update', 'key' => 'side', 'attributes' => ['name' => 'Side tent']]]],
            'a row attached to a relation to one' => ['/shows/mutate', [['operation' => 'update', 'key' => 2, 'relations' => ['stage' => ['operation' => 'attach', 'key' => 'side']]]]],
            'a row created under a relation to many' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'relations' => ['comments' => [['operation' => 'create', 'attributes' => ['rating' => 4, 'body' => 'Great']]]]]]],
            'a list of keys' => ['/stages/mutate', [['operation' => 'update', 'key' => 'side', 'relations' => ['shows' => [['operation' => 'attach', 'key' => [1, 2]]]]]]],
            'pivot fields and a sync that keeps the other rows' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'relations' => ['artists' => [['operation' => 'sync', 'key' => [1], 'pivot' => ['role' => 'guest'], 'without_detaching' => true]]]]]],
            'a morph-to-many relation' => ['/shows/mutate', [['operation' => 'update', 'key' => 2, 'relations' => ['labels' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['position' => 1]]]]]]],
            'a row of each type of a morph relation' => ['/notes/mutate', [
                ['operation' => 'update', 'key' => 1, 'relations' => ['notable' => ['operation' => 'attach', 'type' => Show::class, 'key' => 2]]],
                ['operation' => 'update', 'key' => 2, 'relations' => ['notable' => ['operation' => 'attach', 'type' => Stage::class, 'key' => 'side']]],
            ]],
            'nested relations' => ['/stages/mutate', [['operation' => 'create', 'attributes' => ['code' => 'jazz', 'name' => 'Jazz tent'], 'relations' => ['shows' => [
                ['operation' => 'attach', 'key' => 2, 'relations' => ['artists' => [['operation' => 'attach', 'key' => 1, 'pivot' => ['role' => 'guest']]]]],
            ]]]]],
            'the records of the written rows' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Late show']], ['operation' => 'update', 'key' => 2, 'attributes' => ['featured' => true]]], [
                'selects' => [['field' => 'title']],
                'aggregates' => [['relation' => 'comments', 'type' => 'count']],
                'includes' => [['relation' => 'stage'], ['relation' => 'artists']],
                'gates' => ['view', 'feature'],
            ]],
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $mutate
     * @param  array<string, mixed>  $records
     */
    #[Test]
    #[DataProvider('refusedMutations')]
    public function it_refuses_a_mutation_that_the_document_does_not_describe(string $path, array $mutate, array $records = []): void
    {
        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $body = ['mutate' => $mutate, ...($records === [] ? [] : ['response' => $records])];

        $this->assertNotSame([], (new OpenApiValidator)->requestErrors($this->document(), $path, 'post', $body));
        $this->postJson('api'.$path, $body)->assertUnprocessable();
    }

    /** @return array<string, array{0: string, 1: list<array<string, mixed>>, 2?: array<string, mixed>}> */
    public static function refusedMutations(): array
    {
        return [
            'no entry' => ['/shows/mutate', []],
            'an operation of a relation at the root' => ['/shows/mutate', [['operation' => 'attach', 'key' => 1]]],
            'a key on a created row' => ['/stages/mutate', [['operation' => 'create', 'key' => 'jazz', 'attributes' => ['name' => 'Jazz tent']]]],
            'an updated row without its key' => ['/shows/mutate', [['operation' => 'update', 'attributes' => ['title' => 'Late show']]]],
            'a field that the operation cannot write' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['id' => 3]]]],
            'a value of another type' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['seats' => 'many']]]],
            'a value outside the cases of an enum' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['status' => 'cancelled']]]],
            'null for a field that is not nullable' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => null]]]],
            'attributes on an attach' => ['/shows/mutate', [['operation' => 'update', 'key' => 2, 'relations' => ['stage' => ['operation' => 'attach', 'key' => 'side', 'attributes' => ['name' => 'Side tent']]]]]],
            'a list of keys on a relation to one' => ['/shows/mutate', [['operation' => 'update', 'key' => 2, 'relations' => ['stage' => ['operation' => 'attach', 'key' => ['side']]]]]],
            'an operation that the relation does not take' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'relations' => ['comments' => [['operation' => 'sync', 'key' => [1]]]]]]],
            'the sync option on an attach' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'relations' => ['artists' => [['operation' => 'attach', 'key' => 1, 'without_detaching' => true]]]]]],
            'a pivot on a detach' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'relations' => ['artists' => [['operation' => 'detach', 'key' => 1, 'pivot' => ['role' => 'lead']]]]]]],
            'a relation through another relation' => ['/stages/mutate', [['operation' => 'update', 'key' => 'main', 'relations' => ['comments' => [['operation' => 'attach', 'key' => 1]]]]]],
            'a morph relation without its type' => ['/notes/mutate', [['operation' => 'update', 'key' => 1, 'relations' => ['notable' => ['operation' => 'attach', 'key' => 2]]]]],
            'an unknown key in an entry' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'comment' => 'Late']]],
            'an attribute that the resource does not declare' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['nickname' => 'Ada']]]],
            'a relation that the resource does not declare' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'relations' => ['sponsors' => []]]]],
            'the create gate in the records' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Late show']]], ['gates' => ['create']]],
            'a filter in the records' => ['/shows/mutate', [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Late show']]], ['filters' => [['field' => 'title', 'value' => 'Late show']]]],
        ];
    }

    #[Test]
    public function it_documents_field_examples_that_their_schema_accepts(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($document['components']['schemas'] as $name => $schema) {
            foreach ($schema['properties'] ?? [] as $property => $type) {
                foreach ($type['examples'] ?? [] as $example) {
                    $this->assertSame([], $validator->schemaErrors($document, "#/components/schemas/{$name}/properties/{$property}", $example), "{$name}.{$property}");
                    $checked[] = "{$name}.{$property}";
                }
            }
        }

        $this->assertSame([
            'Show.title',
            'Show.title',
            'Show.price',
            'ShowCreate.title',
            'ShowCreate.title',
            'ShowCreate.price',
            'ShowUpdate.title',
            'ShowUpdate.title',
            'ShowUpdate.price',
            'Stage.code',
            'Stage.name',
            'StageCreate.code',
            'StageCreate.name',
            'StageMutationUpdate.key',
            'StageUpdate.name',
        ], $checked);
    }

    #[Test]
    public function it_reports_a_response_that_the_document_does_not_describe(): void
    {
        $errors = (new OpenApiValidator)->responseErrors($this->document(), '/stages', 'get', 200, '{"data": {"fields": "code"}}');

        $this->assertSame(['/data'], array_keys($errors));
    }

    #[Test]
    public function it_reports_an_example_that_the_schema_of_its_field_refuses(): void
    {
        $errors = (new OpenApiValidator)->schemaErrors($this->document(), '#/components/schemas/Show/properties/seats', 'many');

        $this->assertSame(['/'], array_keys($errors));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, mixed>
     */
    private function mutationExamples(array $document): array
    {
        $examples = [];

        foreach ($document['paths'] as $path => $operations) {
            if (str_ends_with($operations['post']['operationId'] ?? '', '.mutate')) {
                $examples[$path] = $operations['post']['requestBody']['content']['application/json']['examples']['default']['value'];
            }
        }

        return $examples;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array{mutate: list<array{attributes?: array<string, mixed>}>}  $example
     */
    private function declared(array $document, string $path, array $example): bool
    {
        $schemas = $document['components']['schemas'];
        $mutation = $document['paths'][$path]['post']['requestBody']['content']['application/json']['schema']['properties']['mutate']['items']['$ref'];
        $create = $schemas[basename(((array) $schemas[basename($mutation)]['discriminator']['mapping'])['create'])];
        $fields = $schemas[basename($create['properties']['attributes']['$ref'])]['properties'];

        foreach ($example['mutate'][0]['attributes'] ?? [] as $name => $value) {
            if (($fields[$name]['examples'][0] ?? $fields[$name]['enum'][0] ?? null) !== $value) {
                return false;
            }
        }

        return true;
    }

    private function seedFestival(): void
    {
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage'], ['code' => 'side', 'name' => 'Side stage']]);
        DB::table('shows')->insert([
            ['id' => 1, 'stage_code' => 'main', 'title' => 'Opening night', 'summary' => 'First night', 'price' => 12.5, 'seats' => 300, 'featured' => true, 'starts_on' => '2026-07-01', 'starts_at' => '2026-07-01 20:00:00', 'status' => 'on_sale', 'level' => 3, 'settings' => '{"lights":"warm"}', 'secret' => 'backstage', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'stage_code' => null, 'title' => 'Closing night', 'summary' => null, 'price' => null, 'seats' => 0, 'featured' => false, 'starts_on' => '2026-07-03', 'starts_at' => null, 'status' => 'planned', 'level' => 1, 'settings' => null, 'secret' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
        DB::table('artists')->insert([['id' => 1, 'name' => 'Ada']]);
        DB::table('artist_show')->insert([['artist_id' => 1, 'show_id' => 1, 'role' => 'lead']]);
        DB::table('comments')->insert([['show_id' => 1, 'rating' => 5, 'body' => 'Loud']]);
        DB::table('notes')->insert([
            ['id' => 1, 'notable_type' => Show::class, 'notable_id' => '1', 'body' => 'Sound check at six'],
            ['id' => 2, 'notable_type' => Stage::class, 'notable_id' => 'main', 'body' => 'Covered'],
        ]);
        DB::table('labels')->insert([['id' => 1, 'name' => 'Rock']]);
        DB::table('labelables')->insert([['label_id' => 1, 'labelable_type' => Show::class, 'labelable_id' => 1, 'position' => 2]]);
    }
}
