<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine;

use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\Engine\EnginePage;
use Lomkit\Rest\Text\Engine\EngineQuery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class EngineQueryTest extends EngineQueryTestCase
{
    #[Test]
    #[DataProvider('drivers')]
    public function it_reads_the_keys_and_the_total_of_an_engine_page(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $first = $this->search()->page(2, 1);
        $second = $this->search()->page(2, 2);

        $this->assertEquals(new EnginePage(['V-g', 'V-e'], 5), $first);
        $this->assertEquals(new EnginePage(['V-d', 'V-c'], 5), $second);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_searches_the_letters_and_digits_of_the_text_only(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $this->assertEquals(EnginePage::empty(), $this->search(text: 'R%a')->page(10, 1));
        $this->assertEquals(EnginePage::empty(), $this->search(text: 'R_port')->page(10, 1));
        $this->assertEquals(new EnginePage(['V-c'], 1), $this->search(text: '"alpha"!')->page(10, 1));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_filters_and_sorts_on_the_engine_with_converted_values(string $driver): void
    {
        config(['scout.driver' => $driver]);

        $page = $this->search()
            ->where('price', '>=', '7.25')
            ->where('active', '=', '1')
            ->orderBy('price', 'asc')
            ->page(10, 1);

        $this->assertEquals(new EnginePage(['V-a', 'V-g', 'V-d'], 3), $page);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_intersects_the_in_lists_of_the_perimeter_and_of_the_client(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $engine = $this->engine(fn (EngineQuery $query): EngineQuery => $query->whereIn('number', [1, 3, 4, 5]));

        $page = $this->search($engine)
            ->whereIn('number', ['3', 5, 7])
            ->page(10, 1);

        $this->assertEquals(new EnginePage(['V-e', 'V-c'], 2), $page);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_unites_the_not_in_lists_of_the_perimeter_and_of_the_client(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $engine = $this->engine(fn (EngineQuery $query): EngineQuery => $query->whereNotIn('team', ['blue']));

        $page = $this->search($engine)
            ->whereNotIn('team', ['green'])
            ->whereNotIn('team', [])
            ->page(10, 1);

        $this->assertEquals(new EnginePage(['V-e', 'V-c', 'V-a'], 3), $page);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_searches_the_trashed_models_kept_in_the_index(string $driver): void
    {
        config(['scout.driver' => $driver, 'scout.soft_delete' => true]);

        $with = $this->search(trashed: Trashed::With)->page(10, 1);
        $only = $this->search(trashed: Trashed::Only)->page(10, 1);
        $without = $this->search()->page(10, 1);

        $this->assertEquals(new EnginePage(['V-g', 'V-f', 'V-e', 'V-d', 'V-c', 'V-a'], 6), $with);
        $this->assertEquals(new EnginePage(['V-f'], 1), $only);
        $this->assertEquals(new EnginePage(['V-g', 'V-e', 'V-d', 'V-c', 'V-a'], 5), $without);
    }
}
