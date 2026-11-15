<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstGates;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['shares' => FirstGates::class])]
class ExtensionGatesDocumentTest extends DocumentationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        FirstGates::reset();
        Applicability::flush();
        FirstGates::$gates = ['share'];
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        DB::table('stages')->insert([['code' => 'main', 'name' => 'Main stage']]);
        DB::table('shows')->insert([
            ['id' => 1, 'stage_code' => 'main', 'title' => 'Opening night', 'summary' => null, 'price' => 12.5, 'seats' => 300, 'featured' => true, 'starts_on' => '2026-07-01', 'starts_at' => null, 'status' => 'on_sale', 'level' => 3, 'settings' => '[]', 'secret' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
            ['id' => 2, 'stage_code' => 'main', 'title' => 'Closing night', 'summary' => null, 'price' => null, 'seats' => 500, 'featured' => false, 'starts_on' => '2026-07-03', 'starts_at' => null, 'status' => 'planned', 'level' => 1, 'settings' => '[]', 'secret' => null, 'created_at' => '2026-01-01 00:00:00', 'updated_at' => '2026-01-01 00:00:00'],
        ]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        FirstGates::reset();
        Applicability::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_lists_an_extension_gate_in_the_search_enum(): void
    {
        $enum = $this->search($this->document(), '/shows/search')['gates']['items']['enum'];

        $this->assertSame(['view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'feature', 'share'], $enum);
    }

    #[Test]
    public function it_documents_the_answer_of_an_extension_gate_on_a_row(): void
    {
        $gates = $this->document()['components']['schemas']['Show']['properties']['gates']['properties'];

        $answer = ['$ref' => '#/components/schemas/RestGateAnswer'];
        $this->assertSame([
            'authorized_to_view' => $answer,
            'authorized_to_update' => $answer,
            'authorized_to_delete' => $answer,
            'authorized_to_restore' => $answer,
            'authorized_to_force_delete' => $answer,
            'authorized_to_feature' => $answer,
            'authorized_to_share' => $answer,
        ], $gates);
    }

    #[Test]
    public function it_answers_an_extension_gate_as_the_document_describes(): void
    {
        $document = $this->document();
        $body = ['search' => ['gates' => ['share'], 'sorts' => [['field' => 'id']]]];
        $validator = new OpenApiValidator;

        $response = $this->postJson('api/shows/search', $body);

        $denied = ['authorized_to_share' => ['allowed' => false, 'message' => null]];
        $response->assertOk()->assertJsonPath('data.*.gates', [$denied, $denied]);
        $this->assertSame([], $validator->requestErrors($document, '/shows/search', 'post', $body));
        $this->assertSame([], $validator->responseErrors($document, '/shows/search', 'post', 200, (string) $response->getContent()));
    }

    #[Test]
    public function it_lists_an_extension_gate_in_details_as_the_document_describes(): void
    {
        $document = $this->document();

        $response = $this->getJson('api/shows?with=gates');

        $response->assertOk();
        $this->assertSame(['view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'feature', 'share'], $response->json('data.gates'));
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, '/shows', 'get', 200, (string) $response->getContent()));
    }
}
