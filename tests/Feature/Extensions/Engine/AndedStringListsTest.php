<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Illuminate\Auth\GenericUser;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\ResetsExtensionStubs;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderGroupsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReaderInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\FilterCapturingTypesenseEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ListCapturingMeilisearchEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\SettingsClient;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucher;
use Lomkit\Rest\Tests\Fixtures\Ledger\ReaderVoucherController;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;

#[Group('scout')]
class AndedStringListsTest extends EngineSearchTestCase
{
    use ResetsExtensionStubs;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Route::prefix('api')->group(fn () => Rest::resource('reader-vouchers', ReaderVoucherController::class)->register());
        config([
            'rest.extensions' => [ReadersExtension::class, ReaderInstructionsExtension::class],
            'scout.typesense.model-settings.'.ReaderVoucher::class.'.search-parameters.query_by' => 'title',
        ]);
        $this->actingAs(new GenericUser(['id' => 'ann']));
    }

    /**
     * @param  list<string>|null  $groups
     * @param  list<string>  $instructions
     */
    #[Test]
    #[DataProvider('lists')]
    public function it_ands_the_lists_of_a_list_attribute_in_the_filter_of_typesense(?array $groups, array $instructions, string $typesense, string $meilisearch): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        ReadersExtension::$groups = $groups;

        $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'instructions' => array_map(fn (string $name): array => ['name' => $name], $instructions)])->assertOk();

        $this->assertSame([$typesense], $engine->filters);
    }

    /**
     * @param  list<string>|null  $groups
     * @param  list<string>  $instructions
     */
    #[Test]
    #[DataProvider('lists')]
    public function it_ands_the_lists_of_a_list_attribute_in_the_filter_of_meilisearch(?array $groups, array $instructions, string $typesense, string $meilisearch): void
    {
        $engine = new ListCapturingMeilisearchEngine(new SettingsClient(fn (): array => ['maxTotalHits' => 1000]));
        $this->useEngine($engine);
        ReadersExtension::$groups = $groups;

        $this->search('reader-vouchers', ['text' => ['value' => 'report'], 'instructions' => array_map(fn (string $name): array => ['name' => $name], $instructions)])->assertOk();

        $this->assertSame([$meilisearch], $engine->filters);
    }

    /** @return array<string, array{list<string>|null, list<string>, string, string}> */
    public static function lists(): array
    {
        return [
            'two lists from one instruction' => [['ann', 'bob', 'cid'], ['ri.bob-and-cid'], '(readers:=[`bob`]) && (readers:=[`cid`])', '(readers IN ["bob"]) AND (readers IN ["cid"])'],
            'two lists from two instructions' => [['ann', 'bob', 'cid'], ['ri.bob', 'ri.cid'], '(readers:=[`bob`]) && (readers:=[`cid`])', '(readers IN ["bob"]) AND (readers IN ["cid"])'],
            'a list from an instruction beside the list of an extension' => [null, ['ri.bob'], '(readers:=[`ann`]) && (readers:=[`bob`])', '(readers IN ["ann"]) AND (readers IN ["bob"])'],
            'three lists from an extension and two instructions' => [null, ['ri.bob', 'ri.cid'], '(readers:=[`ann`]) && (readers:=[`bob`] && readers:=[`cid`])', '(readers IN ["ann"]) AND (readers IN ["bob"] AND readers IN ["cid"])'],
            'a list from an instruction that narrows the list of an extension' => [['ann', 'bob'], ['ri.bob'], 'readers:=[`bob`]', 'readers IN ["bob"]'],
            'a list from an instruction that the list of an extension narrows' => [['bob'], ['ri.bob-and-cid'], '(readers:=[`bob`]) && (readers:=[`cid`])', '(readers IN ["bob"]) AND (readers IN ["cid"])'],
        ];
    }

    #[Test]
    public function it_ands_the_lists_of_two_extensions(): void
    {
        $engine = new FilterCapturingTypesenseEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ReadersExtension::class, ReaderGroupsExtension::class]]);

        $this->search('reader-vouchers', ['text' => ['value' => 'report']])->assertOk();

        $this->assertSame(['(readers:=[`group-a`]) && (readers:=[`ann`])'], $engine->filters);
    }
}
