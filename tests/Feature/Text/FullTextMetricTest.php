<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Text\Stubs\MeasuredFullTextPostController;
use PHPUnit\Framework\Attributes\Test;

class FullTextMetricTest extends FullTextTestCase
{
    #[Test]
    public function it_counts_the_rows_that_match_the_text_of_a_metric_search(): void
    {
        Rest::resource('measured-full-text-posts', MeasuredFullTextPostController::class)->register();
        $this->createPost('Desk lamp', 'Brass shade', ['views' => 10]);
        $this->createPost('Glass vase', 'Hand blown glass', ['views' => 5]);
        $this->createPost('Floor lamp', 'Tall lamp', ['views' => 200]);
        $this->createPost('Lamp oil', 'Clear oil', ['views' => 5]);

        $this->postJson('/measured-full-text-posts/metrics/row-count', ['search' => ['text' => ['value' => 'lamp'], 'filters' => [['field' => 'views', 'operator' => '<', 'value' => 100]]]])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'count', 'value' => 2]]);
    }
}
