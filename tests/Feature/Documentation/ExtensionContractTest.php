<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\ProgramExtension;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['program' => ProgramExtension::class])]
class ExtensionContractTest extends DocumentationTestCase
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
    }

    #[Test]
    public function it_runs_an_extension_instruction_as_the_document_describes(): void
    {
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();
        $body = ['search' => ['instructions' => [['name' => 'program.only-headliners', 'fields' => [['name' => 'min_seats', 'value' => 100]]]], 'limit' => 25]];
        $validator = new OpenApiValidator;

        $response = $this->postJson('api/shows/search', $body);

        $response->assertOk()->assertJsonPath('data.*.id', [1])->assertJsonPath('per_page', 25);
        $this->assertSame([], $validator->requestErrors($document, '/shows/search', 'post', $body));
        $this->assertSame([], $validator->responseErrors($document, '/shows/search', 'post', 200, (string) $response->getContent()));
    }

    #[Test]
    public function it_lists_an_extension_instruction_in_details_as_the_document_describes(): void
    {
        $this->actingAs(new User(['id' => 1, 'name' => 'Ada']));
        $document = $this->document();

        $response = $this->getJson('api/shows?with=instructions');

        $response->assertOk()->assertJsonPath('data.instructions.*.uriKey', ['only-featured', 'program.only-headliners']);
        $this->assertSame([], (new OpenApiValidator)->responseErrors($document, '/shows', 'get', 200, (string) $response->getContent()));
    }
}
