<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Illuminate\Validation\Rules\Enum as EnumRule;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Scramble\FieldExample;
use Lomkit\Rest\Scramble\FieldSchema;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowLevel;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowStatus;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class OfferedEnumCasesTest extends TestCase
{
    #[Test]
    public function it_offers_every_case_without_offered(): void
    {
        $field = Enum::make('status', ShowStatus::class);

        $this->assertSame(ShowStatus::cases(), $field->offeredCases());
        $this->assertTrue($field->offersEveryCase());
    }

    #[Test]
    public function it_offers_the_listed_cases_in_the_order_of_the_enum(): void
    {
        $field = Enum::make('status', ShowStatus::class)->offered([ShowStatus::OnSale, ShowStatus::Planned, ShowStatus::OnSale]);

        $this->assertSame(['planned', 'on_sale'], $field->offeredValues());
        $this->assertSame(['planned', 'on_sale', 'sold_out'], $field->values());
        $this->assertFalse($field->offersEveryCase());
    }

    #[Test]
    public function it_offers_the_cases_that_the_closure_accepts(): void
    {
        $field = Enum::make('level', ShowLevel::class)->offered(fn (ShowLevel $level): bool => $level !== ShowLevel::Main);

        $this->assertSame([1, 3], $field->offeredValues());
    }

    #[Test]
    public function it_refuses_a_case_of_another_enum(): void
    {
        $field = Enum::make('status', ShowStatus::class)->offered([ShowStatus::Planned, ShowLevel::Open]);

        $this->expectExceptionObject(InvalidFieldException::foreignOfferedCase('status', ShowStatus::class, ShowLevel::Open));

        $field->offeredCases();
    }

    #[Test]
    public function it_names_a_value_that_is_not_a_case(): void
    {
        $this->assertSame(
            'The enum field [status] offers [string], which is not a case of ['.ShowStatus::class.']. Pass cases of '.ShowStatus::class.' to offered().',
            InvalidFieldException::foreignOfferedCase('status', ShowStatus::class, 'planned')->getMessage(),
        );
    }

    #[Test]
    public function it_refuses_an_empty_list(): void
    {
        $field = Enum::make('status', ShowStatus::class)->offered([]);

        $this->expectExceptionObject(InvalidFieldException::noOfferedCase('status'));

        $field->getRules();
    }

    #[Test]
    public function it_refuses_a_closure_that_offers_no_case(): void
    {
        $field = Enum::make('status', ShowStatus::class)->offered(fn (): bool => false);

        $this->expectExceptionObject(InvalidFieldException::noOfferedCase('status'));

        $field->toWriteSchema();
    }

    #[Test]
    public function it_validates_writes_on_the_offered_cases_and_filters_on_every_case(): void
    {
        $field = Enum::make('status', ShowStatus::class)->offered([ShowStatus::Planned]);
        $write = $field->getRules()->shared()[0];
        $filter = $field->baseRules()[0];

        $this->assertInstanceOf(EnumRule::class, $write);
        $this->assertInstanceOf(EnumRule::class, $filter);
        $this->assertTrue($write->passes('status', 'planned'));
        $this->assertFalse($write->passes('status', 'sold_out'));
        $this->assertTrue($filter->passes('status', 'sold_out'));
    }

    #[Test]
    public function it_documents_the_offered_cases_on_writes_and_every_case_elsewhere(): void
    {
        $field = Enum::make('status', ShowStatus::class)
            ->nullable()
            ->offered([ShowStatus::OnSale, ShowStatus::SoldOut]);

        $this->assertSame('{"type":["string","null"],"enum":["planned","on_sale","sold_out",null]}', json_encode(FieldSchema::of($field)->serializeAs31(), JSON_THROW_ON_ERROR));
        $this->assertSame('{"type":["string","null"],"enum":["on_sale","sold_out",null]}', json_encode(FieldSchema::written($field)->serializeAs31(), JSON_THROW_ON_ERROR));
        $this->assertSame('planned', FieldExample::of($field));
        $this->assertSame('on_sale', FieldExample::written($field));
    }
}
