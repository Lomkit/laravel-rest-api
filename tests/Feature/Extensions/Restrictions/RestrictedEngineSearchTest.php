<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstHalfExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondHalfExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class RestrictedEngineSearchTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RestrictionLog::reset();
    }

    #[\Override]
    protected function tearDown(): void
    {
        RestrictionLog::reset();

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_refuses_a_text_search_through_an_engine_that_a_restriction_does_not_reach(string $driver): void
    {
        config(['scout.driver' => $driver, 'rest.extensions' => [HiddenRowsExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::restrictedByExtensions(VoucherResource::class, [['hidden', HiddenRowsExtension::class]]));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_refuses_an_action_on_the_results_of_such_a_search(): void
    {
        config(['rest.extensions' => [HiddenRowsExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::restrictedByExtensions(VoucherResource::class, [['hidden', HiddenRowsExtension::class]]));

        $this->postJson('/api/vouchers/actions/deactivate-vouchers', ['search' => ['text' => ['value' => 'report']]]);
    }

    #[Test]
    public function it_points_the_disabled_extensions_to_the_registered_class_of_a_bound_subclass(): void
    {
        config(['rest.extensions' => [HiddenRowsExtension::class]]);
        $this->app?->bind(HiddenRowsExtension::class, static fn (): HiddenRowsExtension => new class extends HiddenRowsExtension {});
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::restrictedByExtensions(VoucherResource::class, [['hidden', HiddenRowsExtension::class]]));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_names_the_extensions_and_how_to_leave_the_resource_out(): void
    {
        $this->assertSame(
            'The [half_a] extension '.FirstHalfExtension::class.' and the [half_b] extension '.SecondHalfExtension::class.' restrict the rows of '.VoucherResource::class.', whose text search runs on a search engine that these restrictions do not reach. Leave '.VoucherResource::class.' out of them with appliesTo(), add one of these classes to the [rest.disabled_extensions] config, or remove the text search of '.VoucherResource::class.'.',
            InvalidTextSearchException::restrictedByExtensions(VoucherResource::class, [['half_b', SecondHalfExtension::class], ['half_a', FirstHalfExtension::class]])->getMessage(),
        );
    }

    #[Test]
    public function it_still_searches_without_text_on_a_restricted_resource(): void
    {
        config(['rest.extensions' => [HiddenRowsExtension::class]]);

        $response = $this->search('vouchers', ['filters' => [['field' => 'title', 'value' => 'Budget']]]);

        $response->assertOk()->assertJsonPath('data.*.title', ['Budget']);
        $this->assertSame(['VoucherResource guest'], RestrictionLog::$seen);
    }

    #[Test]
    public function it_still_searches_text_with_an_extension_of_another_kind(): void
    {
        config(['rest.extensions' => [NotingExtension::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
    }
}
