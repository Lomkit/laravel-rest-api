<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaGuardResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaStageMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionStageMeta;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['quota' => QuotaStageMeta::class, 'region' => RegionStageMeta::class, 'refusals' => QuotaGuardResponses::class])]
class ExtensionMetaContractTest extends DocumentationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage']]);
        DB::table('shows')->insert([
            ['id' => 1, 'stage_code' => 'main', 'title' => 'Opening night', 'summary' => null, 'price' => 12.5, 'seats' => 300, 'featured' => true, 'starts_on' => '2026-07-01', 'starts_at' => null, 'status' => 'on_sale', 'level' => 3, 'settings' => '[]', 'secret' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'stage_code' => 'main', 'title' => 'Closing night', 'summary' => null, 'price' => null, 'seats' => 500, 'featured' => false, 'starts_on' => '2026-07-03', 'starts_at' => null, 'status' => 'planned', 'level' => 1, 'settings' => '[]', 'secret' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
    }

    #[Test]
    public function it_answers_a_search_with_the_meta_of_each_extension_as_the_document_describes(): void
    {
        $document = $this->document();
        $body = ['search' => ['sorts' => [['field' => 'code']]]];
        $validator = new OpenApiValidator;

        $response = $this->postJson('api/stages/search', $body);

        $response->assertOk();
        $this->assertSame('{"quota":{"remaining":3},"region":{}}', json_encode(self::meta((string) $response->getContent())));
        $this->assertSame([], $validator->requestErrors($document, '/stages/search', 'post', $body));
        $this->assertSame([], $validator->responseErrors($document, '/stages/search', 'post', 200, (string) $response->getContent()));
    }

    #[Test]
    public function it_answers_a_search_of_a_resource_with_search_meta_as_the_document_describes(): void
    {
        $document = $this->document();

        $response = $this->postJson('api/shows/search', ['search' => [
            'instructions' => [['name' => 'only-featured']],
            'gates' => ['create'],
        ]]);

        $response->assertOk();
        $this->assertSame(
            '{"gates":{"authorized_to_create":{"allowed":true,"message":null}},"listed":1,"instructions":{"only-featured":{"featured":true}},"quota":{"remaining":3}}',
            json_encode(self::meta((string) $response->getContent())),
        );
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, '/shows/search', 'post', 200, (string) $response->getContent()));
    }

    /** @return array<string, array{string, string}> */
    public static function details(): array
    {
        return [
            'stages' => ['/stages', '{"quota":{"plan":"free"},"region":{}}'],
            'shows' => ['/shows', '{"label":"Shows","default_sort":{"field":"starts_on","direction":"desc"},"quota":{"plan":"free"}}'],
        ];
    }

    #[Test]
    #[DataProvider('details')]
    public function it_answers_details_with_the_meta_of_each_extension_as_the_document_describes(string $path, string $meta): void
    {
        $document = $this->document();

        $response = $this->getJson('api'.$path);

        $response->assertOk();
        $this->assertSame($meta, json_encode(self::meta((string) $response->getContent(), 'data')));
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, $path, 'get', 200, (string) $response->getContent()));
    }

    #[Test]
    public function it_answers_an_extension_refusal_as_the_document_describes(): void
    {
        $document = $this->document();
        $body = ['mutate' => [['operation' => 'create', 'attributes' => ['code' => 'side', 'name' => 'Side stage']]]];
        $validator = new OpenApiValidator;

        $response = $this->postJson('api/stages/mutate', $body);

        $response->assertStatus(429)->assertExactJson(['message' => 'The monthly quota is reached.']);
        $this->assertSame([], $validator->requestErrors($document, '/stages/mutate', 'post', $body));
        $this->assertSame(['$ref' => '#/components/responses/refusals.Exceeded'], $document['paths']['/stages/mutate']['post']['responses'][429]);
        $this->assertSame([], $validator->schemaErrors($document, '#/components/responses/refusals.Exceeded/content/application~1json/schema', json_decode((string) $response->getContent(), flags: JSON_THROW_ON_ERROR)));
        $this->assertNotSame([], $validator->schemaErrors($document, '#/components/responses/refusals.Exceeded/content/application~1json/schema', (object) []));
        $this->assertSame(['main'], DB::table('stages')->pluck('code')->all());
    }

    private static function meta(string $content, ?string $wrapper = null): mixed
    {
        $decoded = json_decode($content, flags: JSON_THROW_ON_ERROR);
        $body = $wrapper === null ? $decoded : $decoded->{$wrapper};

        return $body->meta;
    }
}
