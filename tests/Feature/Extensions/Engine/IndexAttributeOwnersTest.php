<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DeclaredIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondIndexExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\ScoutEngineDeclarationTestCase;
use Lomkit\Rest\Tests\Fixtures\Ledger\TeamVoucherResource;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\ScoutEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class IndexAttributeOwnersTest extends ScoutEngineDeclarationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        DeclaredIndexExtension::$declared = [];
        config(['scout.driver' => 'collection']);
    }

    #[\Override]
    protected function tearDown(): void
    {
        EngineLog::reset();
        DeclaredIndexExtension::$declared = [];

        parent::tearDown();
    }

    #[Test]
    public function it_refuses_an_attribute_that_the_resource_filters(): void
    {
        $this->declare(['first' => [Integer::make('number')]]);

        $this->expectExceptionObject(InvalidEngineExtensionException::indexAttributeTaken('first', FirstIndexExtension::class, VoucherResource::class, 'number', 'the filterable() list of its ScoutEngine'));

        $this->capabilities($this->open(['number']));
    }

    #[Test]
    public function it_refuses_an_attribute_of_the_resource_perimeter(): void
    {
        $this->declare(['first' => [Text::make('team')]]);
        $text = $this->resource(TeamVoucherResource::class)->text();
        $this->assertInstanceOf(ScoutEngine::class, $text);

        $this->expectExceptionObject(InvalidEngineExtensionException::indexAttributeTaken('first', FirstIndexExtension::class, TeamVoucherResource::class, 'team', 'the perimeter() list of its ScoutEngine'));

        $text->capabilities($this->target(TeamVoucherResource::class));
    }

    /** @param list<string> $order */
    #[Test]
    #[DataProvider('orders')]
    public function it_refuses_an_attribute_that_another_extension_declares_in_any_order(array $order): void
    {
        $extensions = ['first' => FirstIndexExtension::class, 'second' => SecondIndexExtension::class];
        DeclaredIndexExtension::$declared = [FirstIndexExtension::class => [Integer::make('owner_id')], SecondIndexExtension::class => [Integer::make('owner_id')]];
        config(['rest.extensions' => array_combine($order, array_map(fn (string $name): string => $extensions[$name], $order))]);

        $this->expectExceptionObject(InvalidEngineExtensionException::indexAttributeTaken('second', SecondIndexExtension::class, VoucherResource::class, 'owner_id', 'the [first] extension '.FirstIndexExtension::class));

        $this->capabilities($this->open());
    }

    /** @return array<string, array{list<string>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [['first', 'second']],
            'in reverse order' => [['second', 'first']],
        ];
    }

    #[Test]
    public function it_refuses_an_attribute_declared_twice_by_one_extension(): void
    {
        $this->declare(['first' => [Integer::make('owner_id'), Integer::make('owner_id')]]);

        $this->expectExceptionObject(InvalidEngineExtensionException::repeatedIndexAttribute('first', FirstIndexExtension::class, VoucherResource::class, 'owner_id'));

        $this->capabilities($this->open());
    }

    #[Test]
    #[DataProvider('invalidAttributes')]
    public function it_checks_extension_attributes_with_the_rules_of_the_resource(mixed $attribute, InvalidEngineExtensionException $expected): void
    {
        $this->declare(['first' => [Integer::make('owner_id'), $attribute]]);

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
            'The [second] extension '.SecondIndexExtension::class.' declares for '.VoucherResource::class.' the index attribute [owner_id], which the [first] extension '.FirstIndexExtension::class.' already declares. Index attributes keep their real name: index the attribute under another name for one of them, or leave '.VoucherResource::class.' out of the extension with appliesTo().',
            InvalidEngineExtensionException::indexAttributeTaken('second', SecondIndexExtension::class, VoucherResource::class, 'owner_id', 'the [first] extension '.FirstIndexExtension::class)->getMessage(),
        );
        $this->assertTrue(InvalidEngineExtensionException::repeatedIndexAttribute('first', FirstIndexExtension::class, VoucherResource::class, 'owner_id')->namesExtension('first'));
    }

    #[Test]
    public function it_accepts_a_field_that_the_resource_does_not_declare(): void
    {
        $this->declare(['first' => [Integer::make('tenant_id')]]);

        $grammar = $this->capabilities($this->open(['number']))->engine;

        $this->assertNotNull($grammar);
        $this->assertSame(['number'], array_keys($grammar->filters));
    }

    /** @param array<string, list<mixed>> $declared */
    private function declare(array $declared): void
    {
        $classes = ['first' => FirstIndexExtension::class, 'second' => SecondIndexExtension::class];

        foreach ($declared as $name => $attributes) {
            DeclaredIndexExtension::$declared[$classes[$name]] = $attributes;
        }

        config(['rest.extensions' => array_intersect_key($classes, $declared)]);
    }
}
