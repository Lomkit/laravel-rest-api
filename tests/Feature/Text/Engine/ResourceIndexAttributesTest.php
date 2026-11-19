<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Laravel\Scout\Engines\CollectionEngine;
use Laravel\Scout\Engines\DatabaseEngine;
use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DeclaredIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstIndexExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use Lomkit\Rest\Text\Engine\EngineQuery;
use Lomkit\Rest\Text\Engine\ResourceIndexList;
use Lomkit\Rest\Text\Engine\StringListAttribute;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class ResourceIndexAttributesTest extends ScoutEngineDeclarationTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->useEngine(new FakeTypesenseEngine);
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title']);
    }

    #[Test]
    public function it_still_needs_a_perimeter_decision(): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::missingPerimeter(VoucherResource::class));

        $this->capabilities(ScoutEngine::make()->indexAttributes([StringListAttribute::make('name_prefixes')]));
    }

    #[Test]
    public function it_keeps_the_attributes_out_of_the_client_grammar(): void
    {
        $grammar = $this->capabilities($this->open(['number'], ['number'])->indexAttributes([Integer::make('rank'), 'team', StringListAttribute::make('name_prefixes')]))->engine;

        $this->assertNotNull($grammar);
        $this->assertSame([['number'], ['number']], [array_keys($grammar->filters), $grammar->sorts]);
    }

    #[Test]
    #[DataProvider('duplicates')]
    public function it_refuses_a_name_declared_twice(string $declaration): void
    {
        $engine = match ($declaration) {
            'filterable' => $this->open(['team'])->indexAttributes([Text::make('team')]),
            'perimeter' => ScoutEngine::make()->perimeter(fn (): null => null, [Text::make('team')])->indexAttributes([Text::make('team')]),
            default => $this->open()->indexAttributes([StringListAttribute::make('team'), Text::make('team')]),
        };

        $this->expectExceptionObject(InvalidTextSearchException::duplicateAttribute(VoucherResource::class, 'team'));

        $this->capabilities($engine);
    }

    /** @return array<string, array{string}> */
    public static function duplicates(): array
    {
        return [
            'in filterable() and indexAttributes()' => ['filterable'],
            'in perimeter() and indexAttributes()' => ['perimeter'],
            'twice in indexAttributes()' => ['index'],
        ];
    }

    #[Test]
    #[DataProvider('chains')]
    public function it_keeps_the_attributes_through_every_other_declaration(string $method): void
    {
        $declared = [Text::make('label'), StringListAttribute::make('name_prefixes')];
        $engine = match ($method) {
            'filterable' => $this->open()->indexAttributes($declared)->filterable(['number']),
            'sortable' => $this->open(['number'])->indexAttributes($declared)->sortable(['number']),
            'perimeter' => ScoutEngine::make()->indexAttributes($declared)->perimeter(fn (): null => null),
            'withoutPerimeter' => ScoutEngine::make()->indexAttributes($declared)->withoutPerimeter(),
            'servesLists' => $this->open()->indexAttributes($declared)->servesLists(),
            default => $this->open(['number'], ['number'])->servesLists()->indexAttributes($declared)->tieBreaker('number'),
        };

        $query = $this->engineQuery($engine)->where('label', '=', 'a')->whereIn('name_prefixes', ['re']);

        $this->assertSame(['label'], $engine->serverNames());
        $this->assertInstanceOf(EngineQuery::class, $query);
    }

    /** @return array<string, array{string}> */
    public static function chains(): array
    {
        return [
            'filterable()' => ['filterable'],
            'sortable()' => ['sortable'],
            'perimeter()' => ['perimeter'],
            'withoutPerimeter()' => ['withoutPerimeter'],
            'servesLists()' => ['servesLists'],
            'tieBreaker()' => ['tieBreaker'],
        ];
    }

    #[Test]
    public function it_replaces_the_attributes_on_a_second_call(): void
    {
        $engine = $this->open()->indexAttributes([Text::make('label')])->indexAttributes([Integer::make('rank')]);

        $this->assertSame(['rank'], $engine->serverNames());
        $this->expectExceptionObject(InvalidEngineQueryException::undeclaredAttribute('label'));

        $this->engineQuery($engine)->where('label', '=', 'a');
    }

    #[Test]
    public function it_adds_the_attributes_to_those_of_the_perimeter_and_keeps_them_through_a_second_perimeter(): void
    {
        $first = ScoutEngine::make()->perimeter(fn (): null => null, ['team'])->indexAttributes([Text::make('label')]);
        $second = $first->perimeter(fn (EngineQuery $query): EngineQuery => $query->where('label', '=', 'a'));

        $this->assertSame([['team', 'label'], ['label']], [$first->serverNames(), $second->serverNames()]);
        $this->assertInstanceOf(EngineQuery::class, $this->engineQuery($first)->where('team', '=', 'red')->where('label', '=', 'a'));
        $this->assertInstanceOf(EngineQuery::class, $this->engineQuery($second));
    }

    #[Test]
    public function it_accepts_the_same_declaration_from_an_extension(): void
    {
        $this->declare([StringListAttribute::make('name_prefixes'), Text::make('label')]);
        $engine = $this->open(['number'])->indexAttributes([StringListAttribute::make('name_prefixes'), Text::make('label')]);

        $grammar = $this->capabilities($engine)->engine;
        $query = $this->engineQuery($engine)->whereIn('name_prefixes', ['re'])->where('label', '=', 'a');

        $this->assertNotNull($grammar);
        $this->assertSame(['number'], array_keys($grammar->filters));
        $this->assertInstanceOf(EngineQuery::class, $query);
    }

    #[Test]
    #[DataProvider('conflicts')]
    public function it_refuses_an_extension_that_declares_the_name_another_way(string $resource, string $extension): void
    {
        $this->declare([$extension === 'list' ? StringListAttribute::make('label') : Integer::make('label')]);
        $engine = $this->open()->indexAttributes([$resource === 'list' ? StringListAttribute::make('label') : Text::make('label')]);
        $expected = InvalidEngineExtensionException::indexAttributeTakenByResource('first', FirstIndexExtension::class, VoucherResource::class, 'label', ResourceIndexList::IndexAttributes);

        $this->assertStringContainsString('which the indexAttributes() list of its ScoutEngine already declares with another field type or list marker.', $expected->getMessage());
        $this->expectExceptionObject($expected);

        $this->capabilities($engine);
    }

    /** @return array<string, array{string, string}> */
    public static function conflicts(): array
    {
        return [
            'another field type' => ['text', 'integer'],
            'a list where the resource declares a field' => ['text', 'list'],
            'a field where the resource declares a list' => ['list', 'integer'],
        ];
    }

    #[Test]
    #[DataProvider('scalarDrivers')]
    public function it_declares_a_list_on_an_engine_that_cannot_compare_it_and_names_the_perimeter_that_compares_it(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $declared = $this->open(['number'])->indexAttributes([StringListAttribute::make('name_prefixes')]);
        $bounded = ScoutEngine::make()->perimeter(fn (EngineQuery $query): EngineQuery => $query->whereIn('name_prefixes', ['re']))->indexAttributes([StringListAttribute::make('name_prefixes')]);

        $this->assertNotNull($this->capabilities($declared)->engine);
        $this->expectExceptionObject(InvalidEngineQueryException::unmatchedStringList(null, VoucherResource::class, 'name_prefixes', $driver === 'collection' ? CollectionEngine::class : DatabaseEngine::class));

        $this->engineQuery($bounded);
    }

    /** @return array<string, array{string}> */
    public static function scalarDrivers(): array
    {
        return [
            'collection' => ['collection'],
            'database' => ['database'],
        ];
    }

    #[Test]
    #[DataProvider('listDeclarations')]
    public function it_points_a_list_declared_for_clients_or_the_perimeter_to_index_attributes(string $declaration): void
    {
        $list = StringListAttribute::make('name_prefixes');
        $engine = $declaration === 'filterable()' ? $this->open([$list]) : ScoutEngine::make()->perimeter(fn (): null => null, [$list]);
        $expected = InvalidTextSearchException::notAnAttribute(VoucherResource::class, $declaration, 0, $list);

        $this->assertSame('The ScoutEngine of the resource ['.VoucherResource::class."] declares in {$declaration} a value of type [".StringListAttribute::class."] at position 0. Declare a field, or the name of a field of the resource; declare a list of strings, such as StringListAttribute::make('readers'), in indexAttributes() only.", $expected->getMessage());
        $this->expectExceptionObject($expected);

        $this->capabilities($engine);
    }

    /** @return array<string, array{string}> */
    public static function listDeclarations(): array
    {
        return [
            'filterable()' => ['filterable()'],
            'perimeter()' => ['perimeter()'],
        ];
    }

    #[Test]
    #[DataProvider('reserved')]
    public function it_refuses_the_reserved_soft_delete_name(string $kind): void
    {
        $this->expectExceptionObject(InvalidTextSearchException::invalidAttributeName(VoucherResource::class, '__soft_deleted'));

        $this->capabilities($this->open()->indexAttributes([$kind === 'list' ? StringListAttribute::make('__soft_deleted') : Boolean::make('__soft_deleted')]));
    }

    /** @return array<string, array{string}> */
    public static function reserved(): array
    {
        return [
            'a list' => ['list'],
            'a field' => ['field'],
        ];
    }

    #[Test]
    public function it_names_the_source_of_an_unmatched_list(): void
    {
        $this->assertSame(
            'ScoutEngine::perimeter() of '.VoucherResource::class.' compares the list attribute [name_prefixes], but the engine ['.CollectionEngine::class.'] cannot compare a list of strings: only an engine whose profile implements Lomkit\\Rest\\Text\\Engine\\Profiles\\MatchesStringLists does, such as the built-in Typesense, Meilisearch, Turbopuffer and Algolia profiles. Index the model on such an engine, or compare an attribute that holds a single value on this one.',
            InvalidEngineQueryException::unmatchedStringList(null, VoucherResource::class, 'name_prefixes', CollectionEngine::class)->getMessage(),
        );
        $this->assertSame(
            'The engine query has no attribute [name_prefixes]. Declare it with ScoutEngine::filterable() for clients, with ScoutEngine::indexAttributes() for the server only, or in indexAttributes() of an extension that implements DeclaresIndexAttributes.',
            InvalidEngineQueryException::undeclaredAttribute('name_prefixes')->getMessage(),
        );
    }

    /** @param list<mixed> $declared */
    private function declare(array $declared): void
    {
        DeclaredIndexExtension::$declared = [FirstIndexExtension::class => $declared];

        config(['rest.extensions' => [FirstIndexExtension::class]]);
    }

    private function engineQuery(ScoutEngine $engine): EngineQuery
    {
        return $engine->query(new TextQuery('report'), $this->target(VoucherResource::class));
    }
}
