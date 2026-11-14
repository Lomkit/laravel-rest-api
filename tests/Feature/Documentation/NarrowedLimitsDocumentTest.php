<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Extensions\Contexts\LimitsContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstNarrowing;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['plan' => FirstNarrowing::class])]
class NarrowedLimitsDocumentTest extends DocumentationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Applicability::flush();
        FirstNarrowing::$narrow = static function (LimitsContext $context): void {
            if ($context->user !== null) {
                $context->allowSizes([10, 25]);
            }
        };
    }

    #[\Override]
    protected function tearDown(): void
    {
        FirstNarrowing::$narrow = null;
        Applicability::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_documents_the_limits_of_a_guest_for_a_logged_in_viewer(): void
    {
        $this->actingAs(new GenericUser(['id' => 1]));

        $search = $this->search($this->document(), '/shows/search');

        $this->assertSame('{"type":"integer","enum":[10,25,50]}', json_encode($search['limit']));
    }

    #[Test]
    public function it_documents_an_optional_capped_total_when_an_extension_may_cap(): void
    {
        $schemas = $this->document()['components']['schemas'];

        $this->assertArrayHasKey('total_capped', $schemas['ShowPaginated']['properties']);
        $this->assertNotContains('total_capped', $schemas['ShowPaginated']['required']);
    }
}
