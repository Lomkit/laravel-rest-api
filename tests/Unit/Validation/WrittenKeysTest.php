<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\MessageBook;
use Lomkit\Rest\Validation\Violations;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class WrittenKeysTest extends TestCase
{
    private MessageBook $book;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->book = new MessageBook;
    }

    #[Test]
    public function it_names_the_parsed_keys_as_written(): void
    {
        $this->book->written([
            'mutate',
            'mutate.0.key',
            'mutate.0.without_detaching',
            'mutate.0.relations.latestLoan.operation',
            'search.filters.0.a\.b_c',
            'mutate.0.attributes.metadata.tags_list.*',
        ]);

        $this->assertSame(
            [
                'mutate.0.without_detaching' => 'mutate.0.without_detaching',
                'mutate.0.relations.latestLoan.operation' => 'mutate.0.relations.latestLoan.operation',
                'search.filters.0.a.b_c' => 'search.filters.0.a.b_c',
            ],
            $this->book->attributeNames([], []),
        );
    }

    #[Test]
    public function it_names_a_key_that_laravel_would_reformat_as_written(): void
    {
        $this->book->written(['search.filters.0.a  b', "search.filters.0.\u{24B6}"]);

        $this->assertSame(
            ['search.filters.0.a  b' => 'search.filters.0.a  b', "search.filters.0.\u{24B6}" => "search.filters.0.\u{24B6}"],
            $this->book->attributeNames([], []),
        );
    }

    #[Test]
    public function it_names_a_labelled_key_by_its_label_only(): void
    {
        $value = Cursor::of(['mutate' => [[]]], new Violations)
            ->at('mutate')
            ->item(0)
            ->at('attributes')
            ->at('unit_price');

        $this->book->field($value, Integer::make('unit_price')->label('unit price'));
        $this->book->written([$value->path]);

        $this->assertSame(['mutate.*.attributes.unit_price' => 'unit price'], $this->book->attributeNames([], []));
    }

    #[Test]
    public function it_prefers_the_default_name_to_the_written_key(): void
    {
        $this->book->label('fields.min_views', 'min views');
        $this->book->written(['fields.min_views']);

        $this->assertSame(['fields.min_views' => 'min views'], $this->book->attributeNames([], []));
    }

    #[Test]
    public function it_leaves_out_a_written_key_that_a_translation_names(): void
    {
        $this->book->written(['mutate.0.without_detaching']);

        $this->assertSame([], $this->book->attributeNames([], ['mutate.*.without_detaching' => 'keep']));
    }
}
