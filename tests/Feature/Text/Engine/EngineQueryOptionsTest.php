<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FakeTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineQueryOptionsTest extends EngineQueryContractTestCase
{
    #[Test]
    public function it_refuses_a_sort_on_an_attribute_that_is_not_sortable(): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::notSortable('code'));

        $this->engineQuery()->orderBy('code');
    }

    #[Test]
    public function it_refuses_more_sorts_than_the_engine_reads(): void
    {
        $this->useEngine(new FakeTypesenseEngine);
        config(['scout.typesense.model-settings.'.Voucher::class.'.search-parameters.query_by' => 'title,code']);

        $query = $this->engineQuery()
            ->orderBy('price')
            ->orderBy('number')
            ->orderBy('price', 'desc');

        $this->expectExceptionObject(InvalidEngineQueryException::tooManySorts(3));

        $query->orderBy('number', 'desc');
    }

    #[Test]
    public function it_merges_the_options_and_accepts_the_same_value_twice(): void
    {
        $this->engineQuery()
            ->options(['consistency' => 'strong'])
            ->options(['consistency' => 'strong', 'timeout' => 5])
            ->page(10, 1);

        $this->assertSame(['consistency' => 'strong', 'timeout' => 5], $this->engine->searches[0]->options);
    }

    #[Test]
    public function it_refuses_an_option_already_set_to_another_value(): void
    {
        $query = $this->engineQuery()->options(['consistency' => 'strong']);

        $this->expectExceptionObject(InvalidEngineQueryException::conflictingOption('consistency'));

        $query->options(['consistency' => 'eventual']);
    }

    #[Test]
    public function it_refuses_an_option_without_a_name(): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::invalidOption(0));

        $this->engineQuery()->options(['strong']);
    }

    #[Test]
    public function it_refuses_an_option_the_engine_profile_owns(): void
    {
        $this->useEngine(new FakeMeilisearchEngine(new SettingsClient(fn (): array => [])));

        $this->expectExceptionObject(InvalidEngineQueryException::ownedOption('filter'));

        $this->engineQuery()->options(['filter' => 'team = blue']);
    }

    #[Test]
    public function it_refuses_the_trashed_models_when_the_index_does_not_keep_them(): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::trashedUnavailable(Voucher::class));

        $this->engineQuery(Trashed::With);
    }

    #[Test]
    #[DataProvider('invalidPages')]
    public function it_refuses_a_page_outside_the_engine_pagination(int $perPage, int $page): void
    {
        $this->expectExceptionObject(InvalidEngineQueryException::invalidPage($perPage, $page));

        $this->engineQuery()->page($perPage, $page);
    }

    /** @return array<string, array{int, int}> */
    public static function invalidPages(): array
    {
        return [
            'no result per page' => [0, 1],
            'page zero' => [10, 0],
        ];
    }
}
