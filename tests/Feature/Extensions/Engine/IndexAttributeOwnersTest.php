<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DeclaredIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondIndexExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\ScoutEngineDeclarationTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\ResourceIndexList;
use Lomkit\Rest\Text\Engine\StringListAttribute;
use Lomkit\Rest\Text\ScoutEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class IndexAttributeOwnersTest extends ScoutEngineDeclarationTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['scout.driver' => 'collection']);
    }

    #[Test]
    public function it_refuses_an_attribute_that_the_resource_filters(): void
    {
        $this->declare([FirstIndexExtension::class => [Integer::make('number')]]);

        $this->expectExceptionObject(InvalidEngineExtensionException::indexAttributeTakenByResource('first', FirstIndexExtension::class, VoucherResource::class, 'number', ResourceIndexList::Filterable));

        $this->capabilities($this->open(['number']));
    }

    #[Test]
    public function it_refuses_an_attribute_that_the_perimeter_declares_with_another_type(): void
    {
        $this->declare([FirstIndexExtension::class => [Integer::make('team')]]);
        $text = $this->resource(TeamVoucherResource::class)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);

        $this->expectExceptionObject(InvalidEngineExtensionException::indexAttributeTakenByResource('first', FirstIndexExtension::class, TeamVoucherResource::class, 'team', ResourceIndexList::Perimeter));

        $text->capabilities($this->target(TeamVoucherResource::class));
    }

    /** @param list<class-string> $order */
    #[Test]
    #[DataProvider('orders')]
    public function it_refuses_an_attribute_that_another_extension_declares_with_another_type_in_any_order(array $order): void
    {
        DeclaredIndexExtension::$declared = [FirstIndexExtension::class => [Integer::make('owner_id')], SecondIndexExtension::class => [Text::make('owner_id')]];
        config(['rest.extensions' => $order]);

        $this->expectExceptionObject(InvalidEngineExtensionException::indexAttributeTakenByExtension('second', SecondIndexExtension::class, VoucherResource::class, 'owner_id', 'first', FirstIndexExtension::class));

        $this->capabilities($this->open());
    }

    /** @param list<class-string> $order */
    #[Test]
    #[DataProvider('orders')]
    public function it_accepts_one_attribute_declared_alike_by_two_extensions_in_any_order(array $order): void
    {
        DeclaredIndexExtension::$declared = [FirstIndexExtension::class => [Integer::make('owner_id')], SecondIndexExtension::class => [Integer::make('owner_id')]];
        config(['rest.extensions' => $order]);

        $grammar = $this->capabilities($this->open(['number']))->engine;

        $this->assertNotNull($grammar);
        $this->assertSame(['number'], array_keys($grammar->filters));
    }

    #[Test]
    public function it_accepts_an_attribute_that_the_perimeter_declares_the_same_way(): void
    {
        $this->declare([FirstIndexExtension::class => [Text::make('team')]]);
        $text = $this->resource(TeamVoucherResource::class)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);

        $grammar = $text->capabilities($this->target(TeamVoucherResource::class))->engine;

        $this->assertNotNull($grammar);
        $this->assertSame(['number', 'price', 'active', 'code'], array_keys($grammar->filters));
    }

    #[Test]
    public function it_refuses_an_attribute_that_another_extension_declares_as_a_list(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
        $this->declare([FirstIndexExtension::class => [Text::make('owner_id')], SecondIndexExtension::class => [StringListAttribute::make('owner_id')]]);

        $this->expectExceptionObject(InvalidEngineExtensionException::indexAttributeTakenByExtension('second', SecondIndexExtension::class, VoucherResource::class, 'owner_id', 'first', FirstIndexExtension::class));

        $this->capabilities($this->open());
    }

    /** @return array<string, array{list<class-string>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [[FirstIndexExtension::class, SecondIndexExtension::class]],
            'in reverse order' => [[SecondIndexExtension::class, FirstIndexExtension::class]],
        ];
    }

    #[Test]
    public function it_refuses_an_attribute_declared_twice_by_one_extension_and_names_the_registered_class(): void
    {
        $bound = new class extends FirstIndexExtension {};
        $this->app?->bind(FirstIndexExtension::class, static fn (): FirstIndexExtension => $bound);
        $this->declare([FirstIndexExtension::class => []]);
        DeclaredIndexExtension::$declared[$bound::class] = [Integer::make('owner_id'), Integer::make('owner_id')];

        try {
            $this->capabilities($this->open());
            $this->fail('The repeated attribute was accepted.');
        } catch (InvalidEngineExtensionException $exception) {
            $this->assertSame(InvalidEngineExtensionException::repeatedIndexAttribute('first', FirstIndexExtension::class, VoucherResource::class, 'owner_id')->getMessage(), $exception->getMessage());
            $this->assertTrue($exception->concerns(FirstIndexExtension::class));
        }
    }

    #[Test]
    #[DataProvider('invalidAttributes')]
    public function it_checks_extension_attributes_with_the_rules_of_the_resource(mixed $attribute, InvalidEngineExtensionException $expected): void
    {
        $this->declare([FirstIndexExtension::class => [Integer::make('owner_id'), $attribute]]);

        $this->expectExceptionObject($expected);

        $this->capabilities($this->open());
    }

    /** @return array<string, array{mixed, InvalidEngineExtensionException}> */
    public static function invalidAttributes(): array
    {
        $class = FirstIndexExtension::class;
        $resource = VoucherResource::class;

        return [
            'a name that starts with a digit' => [Integer::make('1st'), InvalidEngineExtensionException::invalidIndexAttributeName('first', $class, $resource, '1st')],
            'the reserved soft delete name' => [Text::make('__soft_deleted'), InvalidEngineExtensionException::invalidIndexAttributeName('first', $class, $resource, '__soft_deleted')],
            'a date' => [Date::make('due'), InvalidEngineExtensionException::unsupportedIndexAttributeType('first', $class, $resource, 'due', Date::class)],
            'a filter callback' => [Integer::make('rank')->filterUsing(fn (): null => null), InvalidEngineExtensionException::callbackIndexAttribute('first', $class, $resource, 'rank')],
            'an unfilterable field' => [Text::make('label')->filterable(false), InvalidEngineExtensionException::unfilterableIndexAttribute('first', $class, $resource, 'label')],
            'a field name' => ['number', InvalidEngineExtensionException::notAnIndexAttribute('first', $class, $resource, 1, 'string')],
            'an integer' => [42, InvalidEngineExtensionException::notAnIndexAttribute('first', $class, $resource, 1, 'int')],
        ];
    }

    #[Test]
    public function it_names_the_extension_the_resource_and_both_owners(): void
    {
        $this->assertSame(
            'The [second] extension '.SecondIndexExtension::class.' declares for '.VoucherResource::class.' the index attribute [owner_id], which the [first] extension '.FirstIndexExtension::class.' already declares with another field type or list marker. Index attributes keep their real name: declare it the same way, index it under another name for one of them, or leave '.VoucherResource::class.' out of the extension with appliesTo().',
            InvalidEngineExtensionException::indexAttributeTakenByExtension('second', SecondIndexExtension::class, VoucherResource::class, 'owner_id', 'first', FirstIndexExtension::class)->getMessage(),
        );
        $this->assertSame(
            'The [first] extension '.FirstIndexExtension::class.' declares for '.TeamVoucherResource::class.' the index attribute [team], which the perimeter() list of its ScoutEngine already declares with another field type or list marker. Index attributes keep their real name: declare it the same way, index it under another name for one of them, or leave '.TeamVoucherResource::class.' out of the extension with appliesTo().',
            InvalidEngineExtensionException::indexAttributeTakenByResource('first', FirstIndexExtension::class, TeamVoucherResource::class, 'team', ResourceIndexList::Perimeter)->getMessage(),
        );
        $repeated = InvalidEngineExtensionException::repeatedIndexAttribute('first', FirstIndexExtension::class, VoucherResource::class, 'owner_id');
        $this->assertSame([true, false], [$repeated->concerns(FirstIndexExtension::class), $repeated->concerns('first')]);
    }

    #[Test]
    public function it_accepts_a_field_that_the_resource_does_not_declare(): void
    {
        $this->declare([FirstIndexExtension::class => [Integer::make('tenant_id')]]);

        $grammar = $this->capabilities($this->open(['number']))->engine;

        $this->assertNotNull($grammar);
        $this->assertSame(['number'], array_keys($grammar->filters));
    }

    /** @param array<class-string<DeclaredIndexExtension>, list<mixed>> $declared */
    private function declare(array $declared): void
    {
        DeclaredIndexExtension::$declared = [...DeclaredIndexExtension::$declared, ...$declared];

        config(['rest.extensions' => array_keys($declared)]);
    }
}
