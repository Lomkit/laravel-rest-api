<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Validation\CompiledRules;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class CompiledRulesTest extends TestCase
{
    #[Test]
    public function it_adds_the_projected_values_to_the_input(): void
    {
        $compiled = new CompiledRules([], [], ['fields.limit' => 25, 'search.fields.status' => 'draft']);

        $this->assertSame(
            ['fields' => [['name' => 'limit', 'value' => 25], 'limit' => 25], 'search' => ['fields' => ['status' => 'draft']]],
            $compiled->data(['fields' => [['name' => 'limit', 'value' => 25]]]),
        );
    }

    #[Test]
    public function it_gives_the_application_attribute_names_precedence_over_the_labels(): void
    {
        $compiled = new CompiledRules([], [
            'fields.min_views' => 'min views',
            'mutate.0.relations.lineItems.0.operation' => 'mutate.0.relations.lineItems.0.operation',
            'mutate.0.relations.lineItems.1.operation' => 'mutate.0.relations.lineItems.1.operation',
            'fields.status' => 'status',
        ], []);

        $this->assertSame(
            [
                'fields.min_views' => 'minimum views',
                'mutate.*.relations.lineItems.*.operation' => 'line operation',
                'fields.status' => 'status',
            ],
            $compiled->attributeNames(['fields.min_views' => 'minimum views', 'mutate.*.relations.lineItems.*.operation' => 'line operation']),
        );
    }

    #[Test]
    public function it_leaves_out_the_labels_of_keys_that_a_translation_names(): void
    {
        $compiled = new CompiledRules([], [
            'items.0.unit_price' => 'items.0.unit_price',
            'lineItems.0.unitPrice' => 'lineItems.0.unitPrice',
            'fields.status' => 'status',
            'fields.min_views' => 'min views',
        ], []);

        $this->assertSame(
            ['fields.min_views' => 'minimum views', 'lineItems.0.unitPrice' => 'lineItems.0.unitPrice'],
            $compiled->attributeNames(['fields.min_views' => 'minimum views'], ['items.*.unit_price' => 'unit price', 'fields.status' => 'publication status']),
        );
    }
}
