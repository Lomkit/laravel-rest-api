<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\Show;
use Lomkit\Rest\Tests\Fixtures\Festival\Stage;
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

        $this->assertSame(['DELETE /notes default', 'DELETE /shows default', 'POST /shows/restore default', 'DELETE /shows/force default', 'DELETE /stages default'], $checked);
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
