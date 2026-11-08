<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
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
    public function it_sends_each_request_example_of_the_document(): void
    {
        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($document['paths'] as $path => $operations) {
            foreach ($operations as $method => $operation) {
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

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('acceptedSearches')]
    public function it_answers_a_search_that_the_document_describes(string $path, array $search): void
    {
        $this->seedFestival();
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $validator = new OpenApiValidator;

        $this->assertSame([], $validator->requestErrors($document, $path, 'post', ['search' => $search]));

        $response = $this->postJson('api'.$path, ['search' => $search]);

        $response->assertOk();
        $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()));
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function acceptedSearches(): array
    {
        return [
            'a field with its default operator' => ['/shows/search', ['filters' => [['field' => 'title', 'value' => 'Opening night']]]],
            'a pattern' => ['/shows/search', ['filters' => [['field' => 'title', 'operator' => 'like', 'value' => 'Open%']]]],
            'a list of values' => ['/shows/search', ['filters' => [['field' => 'seats', 'operator' => 'in', 'value' => [0, 300]]]]],
            'a range' => ['/shows/search', ['filters' => [['field' => 'starts_on', 'operator' => 'between', 'value' => ['2026-07-01', '2026-07-03']]]]],
            'a null check' => ['/shows/search', ['filters' => [['field' => 'summary', 'operator' => 'is null']]]],
            'a pivot field' => ['/shows/search', ['filters' => [['field' => 'artists.pivot.role', 'value' => 'lead']]]],
            'related rows' => ['/shows/search', ['filters' => [['relation' => 'comments', 'exists' => false, 'nested' => [['field' => 'rating', 'operator' => '>=', 'value' => 4]]]]]],
            'related rows of one type' => ['/notes/search', ['filters' => [['relation' => 'notable', 'types' => [Stage::class => [['field' => 'code', 'value' => 'main']]]]]]],
            'a group joined with or' => ['/shows/search', ['filters' => [['field' => 'featured', 'value' => true], ['type' => 'or', 'nested' => [['field' => 'seats', 'operator' => '>', 'value' => 100]]]]]],
            'a string key' => ['/stages/search', ['filters' => [['field' => 'code', 'operator' => 'in', 'value' => ['main', 'side']]]]],
            'sorts on a field and on a field of a relation' => ['/shows/search', ['sorts' => [['field' => 'stage.name', 'direction' => 'desc'], ['field' => 'title']]]],
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

        $this->assertSame(['Show.title', 'Show.title', 'Show.price', 'Stage.code'], $checked);
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

    private function seedFestival(): void
    {
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage'], ['code' => 'side', 'name' => 'Side stage']]);
        DB::table('shows')->insert([
            ['id' => 1, 'stage_code' => 'main', 'title' => 'Opening night', 'summary' => 'First night', 'price' => 12.5, 'seats' => 300, 'featured' => true, 'starts_on' => '2026-07-01', 'starts_at' => '2026-07-01 20:00:00', 'status' => 'on_sale', 'level' => 3, 'settings' => '{"lights":"warm"}', 'secret' => 'backstage', 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'stage_code' => null, 'title' => 'Closing night', 'summary' => null, 'price' => null, 'seats' => 0, 'featured' => false, 'starts_on' => '2026-07-03', 'starts_at' => null, 'status' => 'planned', 'level' => 1, 'settings' => null, 'secret' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
        DB::table('artists')->insert([['id' => 1, 'name' => 'Ada']]);
        DB::table('artist_show')->insert([['id' => 1, 'artist_id' => 1, 'show_id' => 1, 'role' => 'lead']]);
        DB::table('comments')->insert([['id' => 1, 'show_id' => 1, 'rating' => 5, 'body' => 'Loud']]);
        DB::table('notes')->insert([
            ['id' => 1, 'notable_type' => Show::class, 'notable_id' => '1', 'body' => 'Sound check at six'],
            ['id' => 2, 'notable_type' => Stage::class, 'notable_id' => 'main', 'body' => 'Covered'],
        ]);
        DB::table('labels')->insert([['id' => 1, 'name' => 'Rock']]);
        DB::table('labelables')->insert([['id' => 1, 'label_id' => 1, 'labelable_type' => Show::class, 'labelable_id' => 1, 'position' => 2]]);
    }
}
