<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SearchableFieldTest extends TestCase
{
    #[Test]
    public function it_keeps_a_field_out_of_the_text_search_by_default(): void
    {
        $this->assertFalse(Text::make('title')->isSearchable());
    }

    #[Test]
    public function it_lists_the_searchable_fields_of_a_set(): void
    {
        $fields = FieldSet::of([
            ID::make('id'),
            Text::make('title')->searchable(),
            Text::make('summary')
                ->searchable()
                ->searchable(false),
            Text::make('body')->searchable(),
        ]);

        $this->assertSame(['title', 'body'], $fields->searchable()->names());
    }

    /** @param Closure(): Field $declare */
    #[Test]
    #[DataProvider('refusedDeclarations')]
    public function it_refuses_a_searchable_field_that_has_no_text_column(Closure $declare, InvalidFieldException $expected): void
    {
        $this->expectExceptionObject($expected);

        $declare();
    }

    /** @return array<string, array{Closure(): Field, InvalidFieldException}> */
    public static function refusedDeclarations(): array
    {
        return [
            'a write-only field' => [
                fn (): Field => Text::make('token')
                    ->writeOnly()
                    ->searchable(),
                InvalidFieldException::searchableWriteOnly('token'),
            ],
            'a searchable field made write-only' => [
                fn (): Field => Text::make('token')
                    ->searchable()
                    ->writeOnly(),
                InvalidFieldException::searchableWriteOnly('token'),
            ],
            'a virtual field' => [
                fn (): Field => Text::make('initials')
                    ->virtual()
                    ->searchable(),
                InvalidFieldException::searchableVirtual('initials'),
            ],
            'a searchable field made virtual' => [
                fn (): Field => Text::make('initials')
                    ->searchable()
                    ->virtual(),
                InvalidFieldException::searchableVirtual('initials'),
            ],
            'a json field' => [fn (): Field => Json::make('settings')->searchable(), InvalidFieldException::notSearchableType('settings', 'json')],
            'an integer field' => [fn (): Field => Integer::make('views')->searchable(), InvalidFieldException::notSearchableType('views', 'integer')],
        ];
    }

    #[Test]
    public function it_accepts_a_field_left_out_of_the_text_search_whatever_its_type(): void
    {
        $settings = Json::make('settings')->searchable(false);
        $token = Text::make('token')
            ->searchable(false)
            ->writeOnly()
            ->virtual();

        $this->assertFalse($settings->isSearchable());
        $this->assertFalse($token->isSearchable());
    }
}
