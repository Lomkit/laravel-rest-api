<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstNarrowing;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', [FirstNarrowing::class])]
class NarrowedLimitsDocumentTest extends DocumentationTestCase
{
    /** @var list<?int> */
    private array $narrowed = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        FirstNarrowing::$narrow = function (LimitsContext $context): void {
            $this->narrowed[] = $context->user?->getAuthIdentifier();
            $context->allowSizes([10]);
        };
    }

    #[\Override]
    protected function tearDown(): void
    {
        FirstNarrowing::$narrow = null;

        parent::tearDown();
    }

    #[Test]
    public function it_documents_the_declared_limits_without_narrowing_them(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));

        $search = $this->search($this->document(), '/shows/search');

        $this->assertSame('{"type":"integer","enum":[10,25,50]}', json_encode($search['limit']));
        $this->assertSame([], $this->narrowed);
    }

    #[Test]
    public function it_documents_an_optional_capped_total_when_an_extension_may_cap(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertArrayHasKey('total_capped', $schemas['ShowPaginated']['properties']);
        $this->assertNotContains('total_capped', $schemas['ShowPaginated']['required']);
    }
}
