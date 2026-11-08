<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResourceDetailsTest extends PackageTestCase
{
    use AssertsDetailsPayload;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    public function it_describes_the_scopes_actions_and_instructions_as_typed_inputs(): void
    {
        $this->assertDetailsPayload('posts', $this->getJson('/api/posts?with='.self::EVERY_PART));
    }

    #[Test]
    public function it_describes_the_relations_and_the_instructions_of_a_resource_without_text_search(): void
    {
        $this->assertDetailsPayload('products', $this->getJson('/api/products?with='.self::EVERY_PART));
    }
}
