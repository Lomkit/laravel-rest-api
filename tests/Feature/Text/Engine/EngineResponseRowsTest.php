<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineResponseRowsTest extends EngineSearchTestCase
{
    #[Test]
    public function it_refuses_an_engine_page_over_the_limit(): void
    {
        config(['scout.driver' => 'collection', 'rest.limits.sizes.rows' => 109]);
        $message = 'The search.includes.0.relation field takes the response over the limit of 109 rows: it can render up to 110 rows.';

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'limit' => 10, 'includes' => [['relation' => 'lines']]]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.includes.0.relation' => [$message]]]);
    }

    #[Test]
    public function it_answers_an_engine_page_at_the_limit(): void
    {
        config(['scout.driver' => 'collection', 'rest.limits.sizes.rows' => 110]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'limit' => 10, 'selects' => [['field' => 'id']], 'includes' => [['relation' => 'lines', 'selects' => [['field' => 'id']]]]]);

        $response->assertOk()->assertJsonPath('total', 4);
    }
}
