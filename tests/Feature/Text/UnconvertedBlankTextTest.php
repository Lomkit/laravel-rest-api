<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull;
use Illuminate\Foundation\Http\Middleware\TrimStrings;
use PHPUnit\Framework\Attributes\Test;

class UnconvertedBlankTextTest extends TextSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ConvertEmptyStringsToNull::class, TrimStrings::class]);
    }

    #[Test]
    public function it_runs_the_normal_search_for_a_blank_text_value(): void
    {
        $this->createPost('Desk lamp', null, ['id' => 1]);
        $this->createPost('Glass vase', null, ['id' => 2]);

        $response = $this->search('contains-posts', ['text' => ['value' => '  '], 'sorts' => [['field' => 'id', 'direction' => 'asc']]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1, 2]);
    }

    #[Test]
    public function it_asks_for_a_value_on_a_blank_text_mode(): void
    {
        $message = 'The search.text.mode field must have a value.';

        $response = $this->search('contains-posts', ['text' => ['value' => 'lamp', 'mode' => ' ']]);

        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['search.text.mode' => [$message]]]);
    }
}
