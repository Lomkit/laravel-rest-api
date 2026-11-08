<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
#[WithConfig('auth.providers.users.model', User::class)]
class WriteConfirmationContractTest extends DocumentationTestCase
{
    private const array ON_SALE = ['mutate' => [[
        'operation' => 'update',
        'key' => 2,
        'attributes' => ['status' => 'on_sale'],
        'relations' => [
            'stage' => ['operation' => 'attach', 'key' => 'side'],
            'comments' => [['operation' => 'create', 'attributes' => ['rating' => 4, 'body' => 'Great']]],
        ],
    ]]];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-08 10:00:00');
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage'], ['code' => 'side', 'name' => 'Side stage']]);
        DB::table('shows')->insert([
            ['id' => 1, 'stage_code' => 'main', 'title' => 'Opening night', 'seats' => 300, 'featured' => true, 'starts_on' => '2026-07-01', 'status' => 'on_sale', 'level' => 3, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'stage_code' => null, 'title' => 'Closing night', 'seats' => 0, 'featured' => false, 'starts_on' => '2026-07-03', 'status' => 'planned', 'level' => 1, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
        DB::table('comments')->insert([['show_id' => 1, 'rating' => 5, 'body' => 'Loud']]);
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('confirmedWrites')]
    public function it_answers_the_confirmation_of_a_write_as_the_document_describes(string $method, string $path, array $body, string $message): void
    {
        $document = $this->document();
        $validator = new OpenApiValidator;
        $method = strtolower($method);

        $asked = $this->json($method, 'api'.$path, $body);

        $asked->assertStatus(428)->assertJsonPath('message', $message);
        $this->assertSame(['$ref' => '#/components/responses/RestConfirmationRequired'], $document['paths'][$path][$method]['responses'][428]);
        $this->assertSame([], $validator->schemaErrors($document, '#/components/responses/RestConfirmationRequired/content/application~1json/schema', json_decode((string) $asked->getContent(), flags: JSON_THROW_ON_ERROR)));
        $body['confirmation'] = $asked->json('confirmation.token');
        $this->assertSame([], $validator->requestErrors($document, $path, $method, $body));

        $response = $this->json($method, 'api'.$path, $body);

        $response->assertOk();
        $this->assertSame([], $validator->responseErrors($document, $path, $method, 200, (string) $response->getContent()));
    }

    /** @return array<string, array{string, string, array<string, mixed>, string}> */
    public static function confirmedWrites(): array
    {
        return [
            'mutate' => ['POST', '/shows/mutate', self::ON_SALE, 'Open the sale of 1 shows?'],
            'force delete' => ['DELETE', '/shows/force', ['resources' => [1, 2]], 'Delete 2 shows for good?'],
        ];
    }

    #[Test]
    public function it_writes_no_nested_entry_before_the_confirmation(): void
    {
        $asked = $this->postJson('api/shows/mutate', self::ON_SALE);

        $asked->assertStatus(428)->assertJsonPath('confirmation.data', ['shows' => 1]);
        $this->assertSame(['planned', null, 1], $this->closingNight());

        $this->postJson('api/shows/mutate', [...self::ON_SALE, 'confirmation' => $this->tokenOf($asked)])->assertOk();
        $this->assertSame(['on_sale', 'side', 2], $this->closingNight());
    }

    #[Test]
    public function it_answers_409_on_a_versioned_resource_before_the_confirmation(): void
    {
        $entry = [...self::ON_SALE['mutate'][0], 'key' => 1, 'version' => '2025-12-31T00:00:00.000000Z'];

        $response = $this->postJson('api/shows/mutate', ['mutate' => [$entry]]);

        $response->assertConflict()->assertJsonPath('conflicts.0.version', '2026-01-01T00:00:00.000000Z');
    }

    #[Test]
    public function it_lists_the_confirmation_key_only_on_the_writes_that_declare_it(): void
    {
        $document = $this->document();
        $body = fn (string $path, string $method): array => $document['paths'][$path][$method]['requestBody']['content']['application/json']['schema'];

        $this->assertSame(['type' => 'string', 'description' => 'The token of the 428 answer of this endpoint, sent back with the same body to write'], $body('/shows/force', 'delete')['properties']['confirmation']);
        $this->assertSame(['type' => 'string', 'description' => 'The token of the 428 answer of this endpoint, sent back with the same body to write'], $body('/shows/mutate', 'post')['properties']['confirmation']);
        $this->assertArrayNotHasKey('confirmation', $body('/shows', 'delete')['properties']);
        $this->assertArrayNotHasKey('confirmation', $body('/shows/restore', 'post')['properties']);
        $this->assertArrayNotHasKey('confirmation', $body('/stages/mutate', 'post')['properties']);
    }

    #[Test]
    public function it_lists_restore_and_force_destroy_in_the_details_of_soft_deletable_models_only(): void
    {
        $shows = $this->getJson('api/shows?with=confirmations');
        $stages = $this->getJson('api/stages?with=confirmations');

        $shows->assertOk()->assertJsonPath('data.confirmations', ['mutate' => true, 'destroy' => false, 'restore' => false, 'forceDestroy' => true]);
        $stages->assertOk()->assertJsonPath('data.confirmations', ['mutate' => false, 'destroy' => false]);
    }

    /** @return array{mixed, mixed, int} */
    private function closingNight(): array
    {
        $show = DB::table('shows')->where('id', 2)->first();

        return [$show?->status, $show?->stage_code, DB::table('comments')->count()];
    }

    private function tokenOf(TestResponse $response): string
    {
        $token = $response->json('confirmation.token');
        $this->assertIsString($token);

        return $token;
    }
}
