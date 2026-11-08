<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Ramsey\Uuid\Uuid;
use stdClass;

#[WithConfig('auth.providers.users.model', User::class)]
class ActionContractTest extends DocumentationTestCase
{
    private const string BATCH = '9f0c4d2e-5a1b-4c3d-8e7f-0a1b2c3d4e5f';

    #[\Override]
    protected function defineEnvironment($app): void
    {
        $app['config']->set('queue.batching.database', $app['config']->get('database.default'));
    }

    #[\Override]
    protected function defineDatabaseMigrations(): void
    {
        parent::defineDatabaseMigrations();

        $this->loadMigrationsFrom(__DIR__.'/../../Support/Database/migrations/2023_08_16_095030_create_job_batches_table.php');
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('shows')->insert([
            ['id' => 1, 'title' => 'Opening night', 'seats' => 300, 'featured' => true, 'starts_on' => '2026-07-01', 'status' => 'on_sale', 'level' => 3, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'title' => 'Closing night', 'seats' => 0, 'featured' => false, 'starts_on' => '2026-07-03', 'status' => 'planned', 'level' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
        DB::table('users')->insert(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        Str::createUuidsUsingSequence([Uuid::fromString(self::BATCH)]);
        $this->actingAs(User::query()->findOrFail(1));
    }

    #[Test]
    public function it_documents_action_examples_that_the_request_schema_accepts(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $checked = [];

        foreach ($this->examples($document) as $path => $example) {
            $this->assertSame([], $validator->requestErrors($document, $path, 'post', $example), $path);
            $checked[] = $path;
        }

        $this->assertSame(['/shows/actions/reschedule-shows', '/shows/actions/cancel-shows', '/shows/actions/import-shows', '/shows/actions/archive-shows'], $checked);
    }

    #[Test]
    public function it_sends_each_action_example_built_from_the_examples_and_defaults_of_its_fields(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $sent = [];

        foreach ($this->examples($document) as $path => $example) {
            if (! $this->declared($document, $path, $example)) {
                continue;
            }

            $response = $this->postJson('api'.$path, (array) $example);

            $response->assertOk();
            $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()), $path);
            $sent[] = $path;
        }

        $this->assertSame(['/shows/actions/cancel-shows', '/shows/actions/import-shows', '/shows/actions/archive-shows'], $sent);
    }

    /** @param array<string, mixed>|stdClass $body */
    #[Test]
    #[DataProvider('acceptedActions')]
    public function it_answers_an_action_that_the_document_describes(string $path, array|stdClass $body): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;

        $this->assertSame([], $validator->requestErrors($document, $path, 'post', $body));

        $response = $this->postJson('api'.$path, (array) $body);

        $response->assertOk();
        $this->assertSame([], $validator->responseErrors($document, $path, 'post', 200, (string) $response->getContent()));
    }

    /** @return array<string, array{string, array<string, mixed>|stdClass}> */
    public static function acceptedActions(): array
    {
        return [
            'a search and the fields of the action' => ['/shows/actions/reschedule-shows', ['search' => ['filters' => [['field' => 'featured', 'value' => true]]], 'fields' => [['name' => 'at', 'value' => null], ['name' => 'notify', 'value' => false]]]],
            'every row without a search' => ['/shows/actions/reschedule-shows', new stdClass],
            'a search with sorts and a limit' => ['/shows/actions/reschedule-shows', ['search' => ['sorts' => [['field' => 'title']], 'limit' => 10]]],
            'a field set to null' => ['/shows/actions/cancel-shows', ['resources' => [2], 'fields' => [['name' => 'reason', 'value' => null]]]],
            'an action without rows' => ['/shows/actions/import-shows', new stdClass],
            'a batchable action' => ['/shows/actions/archive-shows', ['search' => ['filters' => [['field' => 'title', 'value' => 'Closing night']]]]],
        ];
    }

    #[Test]
    #[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
    public function it_answers_the_confirmation_of_an_action_as_the_document_describes(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $body = ['resources' => [1, 2], 'fields' => [['name' => 'reason', 'value' => 'Rain']]];

        $asked = $this->postJson('api/shows/actions/cancel-shows', $body);

        $asked->assertStatus(428)->assertJsonPath('confirmation.data', ['rows' => 2]);
        $this->assertSame(['$ref' => '#/components/responses/RestConfirmationRequired'], $document['paths']['/shows/actions/cancel-shows']['post']['responses'][428]);
        $this->assertSame([], $validator->schemaErrors($document, '#/components/responses/RestConfirmationRequired/content/application~1json/schema', json_decode((string) $asked->getContent(), flags: JSON_THROW_ON_ERROR)));
        $body['confirmation'] = $asked->json('confirmation.token');
        $this->assertSame([], $validator->requestErrors($document, '/shows/actions/cancel-shows', 'post', $body));

        $response = $this->postJson('api/shows/actions/cancel-shows', $body);

        $response->assertOk()->assertJsonPath('data.impacted', 2);
        $this->assertSame([], $validator->responseErrors($document, '/shows/actions/cancel-shows', 'post', 200, (string) $response->getContent()));
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('refusedActions')]
    public function it_refuses_an_action_that_the_document_does_not_describe(string $path, array $body): void
    {
        $this->assertNotSame([], (new OpenApiValidator)->requestErrors($this->document(), $path, 'post', $body));
        $this->postJson('api'.$path, $body)->assertUnprocessable();
    }

    /** @return array<string, array{string, array<string, mixed>}> */
    public static function refusedActions(): array
    {
        return [
            'keys on a search action' => ['/shows/actions/reschedule-shows', ['resources' => [1]]],
            'a search on a keyed action' => ['/shows/actions/cancel-shows', ['search' => ['limit' => 10], 'resources' => [1]]],
            'a search on an action without rows' => ['/shows/actions/import-shows', ['search' => ['filters' => [['field' => 'title', 'value' => 'Opening night']]]]],
            'keys on an action without rows' => ['/shows/actions/import-shows', ['resources' => [1]]],
            'no keys' => ['/shows/actions/cancel-shows', ['fields' => [['name' => 'reason', 'value' => 'Rain']]]],
            'an empty list of keys' => ['/shows/actions/cancel-shows', ['resources' => []]],
            'the same key twice' => ['/shows/actions/cancel-shows', ['resources' => [1, 1]]],
            'more keys than the action takes' => ['/shows/actions/cancel-shows', ['resources' => [1, 2, 3]]],
            'a key of another type' => ['/shows/actions/cancel-shows', ['resources' => ['one']]],
            'a field that the action does not declare' => ['/shows/actions/reschedule-shows', ['fields' => [['name' => 'reason', 'value' => 'Rain']]]],
            'a value of another type' => ['/shows/actions/reschedule-shows', ['fields' => [['name' => 'notify', 'value' => 'yes']]]],
            'an unknown key in a field entry' => ['/shows/actions/reschedule-shows', ['fields' => [['name' => 'at', 'value' => null, 'label' => 'Now']]]],
            'fields that are not a list' => ['/shows/actions/reschedule-shows', ['fields' => ['at' => null]]],
            'a field on an action without fields' => ['/shows/actions/archive-shows', ['fields' => [['name' => 'reason', 'value' => 'Rain']]]],
            'a filter on a field that the search does not know' => ['/shows/actions/archive-shows', ['search' => ['filters' => [['field' => 'nickname', 'value' => 'Ada']]]]],
        ];
    }

    #[Test]
    public function it_answers_the_progress_of_a_batch_as_the_document_describes(): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $started = $this->postJson('api/shows/actions/archive-shows');

        $started->assertOk();
        $this->assertSame([], $validator->responseErrors($document, '/shows/actions/archive-shows', 'post', 200, (string) $started->getContent()));

        $response = $this->getJson('api/shows/actions/archive-shows/batches/'.self::BATCH);

        $response->assertOk();
        $this->assertSame([], $validator->responseErrors($document, '/shows/actions/{action}/batches/{batch}', 'get', 200, (string) $response->getContent()));
    }

    /**
     * @param  array<string, mixed>  $document
     * @return array<string, array<string, mixed>|stdClass>
     */
    private function examples(array $document): array
    {
        $examples = [];

        foreach ($document['paths'] as $path => $operations) {
            if (str_contains($operations['post']['operationId'] ?? '', '.operate.')) {
                $examples[$path] = $operations['post']['requestBody']['content']['application/json']['examples']['default']['value'];
            }
        }

        return $examples;
    }

    /**
     * @param  array<string, mixed>  $document
     * @param  array<string, mixed>|stdClass  $example
     */
    private function declared(array $document, string $path, array|stdClass $example): bool
    {
        $fields = $document['paths'][$path]['post']['requestBody']['content']['application/json']['schema']['properties']['fields']['items'] ?? null;
        $values = $this->defaults($path);

        foreach ($fields === null ? [] : $fields['oneOf'] ?? [$fields] as $entry) {
            $declared = $entry['properties']['value']['examples'] ?? $entry['properties']['value']['enum'] ?? [];

            if ($declared !== []) {
                $values[$entry['properties']['name']['const']] = $declared[0];
            }
        }

        foreach (((array) $example)['fields'] ?? [] as $field) {
            if (! array_key_exists($field['name'], $values) || $values[$field['name']] !== $field['value']) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, mixed> */
    private function defaults(string $path): array
    {
        $defaults = [];

        foreach ((new ShowResource)->actions() as $action) {
            if ('/shows/actions/'.$action->uriKey() !== $path) {
                continue;
            }

            foreach ($action->fields() as $field) {
                if ($field->hasDefault()) {
                    $defaults[$field->name] = $field->defaultValue();
                }
            }
        }

        return $defaults;
    }
}
