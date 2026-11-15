<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['mask' => MaskingFields::class])]
class NarrowedFieldsDocumentTest extends DocumentationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MaskingFields::reset();
        Applicability::flush();
        MaskingFields::$narrow = static function (FieldsContext $context): void {
            if ($context->resource instanceof ShowResource) {
                $context->hide('price');
                $context->readOnly('summary');
                $context->unfilterable('seats');
                $context->unsortable('starts_on');
            }
        };
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
        MaskingFields::reset();
        Applicability::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_builds_the_document_without_narrowing_fields(): void
    {
        $narrowed = $this->document();
        config(['rest.extensions' => []]);
        Applicability::flush();

        $this->assertSame(json_encode($this->document()), json_encode($narrowed));
        $this->assertSame([], MaskingFields::$contexts);
    }

    #[Test]
    public function it_answers_a_narrowed_search_as_the_document_describes(): void
    {
        $document = $this->document();
        $body = ['search' => ['sorts' => [['field' => 'id']]]];
        $validator = new OpenApiValidator;

        $response = $this->postJson('api/shows/search', $body);

        $response->assertOk()->assertJsonPath('data', [
            ['id' => 1, 'title' => 'Opening night', 'summary' => null, 'seats' => 300, 'featured' => true, 'starts_on' => '2026-07-01', 'starts_at' => null, 'status' => 'on_sale', 'level' => 3, 'settings' => [], 'updated_at' => '2026-01-01T00:00:00.000000Z'],
            ['id' => 2, 'title' => 'Closing night', 'summary' => null, 'seats' => 500, 'featured' => false, 'starts_on' => '2026-07-03', 'starts_at' => null, 'status' => 'planned', 'level' => 1, 'settings' => [], 'updated_at' => '2026-01-01T00:00:00.000000Z'],
        ]);
        $this->assertSame([], $validator->requestErrors($document, '/shows/search', 'post', $body));
        $this->assertSame([], $validator->responseErrors($document, '/shows/search', 'post', 200, (string) $response->getContent()));
    }
}
