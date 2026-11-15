<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RedTeamIndexExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ExtensionIndexAttributesTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        config(['rest.extensions' => [RedTeamIndexExtension::class]]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        EngineLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_lets_an_instruction_filter_on_an_attribute_that_an_extension_declares(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'idx.red-team']]]);

        $response->assertOk()->assertExactJson($this->page([5, 1, 3], 3));
    }

    #[Test]
    public function it_keeps_extension_attributes_out_of_the_client_grammar(): void
    {
        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'filters' => [['field' => 'team', 'value' => 'red']]]);

        $response->assertStatus(422)->assertExactJson([
            'message' => 'The selected search.filters.0.field is invalid.',
            'errors' => ['search.filters.0.field' => ['The selected search.filters.0.field is invalid.']],
        ]);
    }

    #[Test]
    public function it_keeps_extension_attributes_out_of_details(): void
    {
        $response = $this->getJson('/api/vouchers?with=text');

        $response->assertOk()->assertJsonPath('data.text.engine.filters.*.name', ['number', 'price', 'active', 'code']);
    }

    #[Test]
    public function it_reads_the_declarations_once_per_request(): void
    {
        $this->search('vouchers', ['text' => ['value' => 'report'], 'instructions' => [['name' => 'idx.red-team']]])->assertOk();

        $this->assertSame(['indexAttributes'], EngineLog::$calls);
    }

    #[Test]
    public function it_gives_the_declaration_the_resource_class_only(): void
    {
        $this->search('vouchers', ['text' => ['value' => 'report']])->assertOk();

        $context = EngineLog::$contexts[0];
        $this->assertInstanceOf(IndexAttributesContext::class, $context);
        $this->assertSame([VoucherResource::class, false], [$context->resource, $context->matchesStringLists]);
    }
}
