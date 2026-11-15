<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainEngineVoucherController;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainEngineVoucherResource;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\PlainProfile;
use Lomkit\Rest\Text\Engine\Profiles\AppliesRowRestrictions;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\MatchesStringLists;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;

#[Group('scout')]
class EngineCapabilitiesTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        PlainProfile::reset();
        Route::prefix('api')->group(function (): void {
            Rest::resource('plain-engine-vouchers', PlainEngineVoucherController::class)->register();
        });
    }

    #[\Override]
    protected function tearDown(): void
    {
        PlainProfile::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_searches_through_a_profile_that_implements_only_the_engine_profile(): void
    {
        $response = $this->search('plain-engine-vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame([[50, 1]], PlainProfile::$pages);
    }

    #[Test]
    public function it_refuses_a_restriction_on_a_profile_without_row_restrictions(): void
    {
        config(['rest.extensions' => ['hidden' => HiddenRowsExtension::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unrestrictedEngine(PlainEngineVoucherResource::class, 'hidden', HiddenRowsExtension::class, PlainProfile::NAME));

        $this->search('plain-engine-vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_keeps_the_engine_profile_methods_and_reads_the_capabilities_from_interfaces(): void
    {
        $methods = array_map(fn (ReflectionMethod $method): string => $method->getName(), (new ReflectionClass(EngineProfile::class))->getMethods());
        sort($methods);

        $this->assertSame(['ensureUsableBy', 'exactTotal', 'literal', 'maxPageSize', 'name', 'operators', 'ownedOptions', 'page', 'reachable', 'sortKeys', 'window'], $methods);
        $this->assertSame(4, (new ReflectionMethod(EngineProfile::class, 'page'))->getNumberOfParameters());
        $this->assertSame([true, true], [is_subclass_of(MatchesStringLists::class, EngineProfile::class), is_subclass_of(AppliesRowRestrictions::class, EngineProfile::class)]);
        $declared = array_filter((new ReflectionClass(AppliesRowRestrictions::class))->getMethods(), fn (ReflectionMethod $method): bool => $method->class === AppliesRowRestrictions::class);
        $this->assertSame(['restrictedPage'], array_values(array_map(fn (ReflectionMethod $method): string => $method->getName(), $declared)));
    }
}
