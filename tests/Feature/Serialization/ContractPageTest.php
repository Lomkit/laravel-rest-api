<?php

namespace Lomkit\Rest\Tests\Feature\Serialization;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Serialization\Stubs\CountedPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ContractPageTest extends PackageTestCase
{
    use SeedsPublishingRows;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPublishingRows();
    }

    #[Test]
    public function it_renders_a_page_that_implements_the_paginator_contract(): void
    {
        Rest::resource('counted-posts', CountedPostController::class)->register();

        $response = $this->postJson('/counted-posts/search', ['search' => [
            'selects' => [['field' => 'id']],
            'sorts' => [['field' => 'id']],
        ]]);

        $response->assertOk();
        $this->assertSame(
            '{"current_page":1,"data":[{"id":1},{"id":2},{"id":3}],"from":1,"last_page":1,"per_page":50,"to":3,"total":3,"meta":[]}',
            $response->getContent(),
        );
    }
}
