<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\MissingColumnException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\MetaVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineSearchMetaTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('meta-vouchers', MetaVoucherController::class)->register();
        Model::preventAccessingMissingAttributes(false);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_adds_the_meta_of_the_resource_and_of_its_instructions_to_an_engine_page(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $response = $this->postJson('/meta-vouchers/search', ['search' => [
            'text' => ['value' => 'report'],
            'instructions' => [['name' => 'noted-numbered-from', 'fields' => [['name' => 'min', 'value' => 3]]]],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', [5, 4, 1]);
        $this->assertSame(['text' => 'report', 'numbers' => 12, 'instructions' => ['noted-numbered-from' => ['min' => 3]]], $response->json('meta'));
    }

    #[Test]
    public function it_refuses_a_search_meta_that_reads_a_column_that_an_engine_page_did_not_load(): void
    {
        $response = $this->postJson('/meta-vouchers/search', ['search' => ['text' => ['value' => 'report'], 'selects' => [['field' => 'title']]]]);

        $response->assertStatus(500);
        $this->assertInstanceOf(MissingColumnException::class, $response->exception);
        $this->assertFalse(Model::preventsAccessingMissingAttributes());
    }
}
