<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CollectionEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EarlyForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RivalEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\UnappliedEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\UnsortedForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Text\Engine\EngineSearchTestCase;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignEngine;
use Lomkit\Rest\Tests\Feature\Text\Engine\Stubs\ForeignProfile;
use Lomkit\Rest\Tests\Fixtures\Ledger\VoucherResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionProperty;

#[Group('scout')]
class EngineProfileProvidersTest extends EngineSearchTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        EngineLog::reset();
        ForeignProfile::reset();
    }

    #[\Override]
    protected function tearDown(): void
    {
        EngineLog::reset();
        ForeignProfile::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_refuses_an_engine_without_a_profile(): void
    {
        $this->useEngine(new ForeignEngine);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unsupportedEngine(VoucherResource::class, ForeignEngine::class));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_searches_an_engine_with_the_profile_an_extension_provides(): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [ForeignEngineProfiles::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report'], 'limit' => 10]);

        $response->assertOk()->assertExactJson($this->page([3], 1, 10));
        $this->assertSame([[10, 1, null]], ForeignProfile::$pages);
    }

    #[Test]
    public function it_lets_an_extension_replace_the_profile_of_a_scout_engine(): void
    {
        config(['rest.extensions' => [CollectionEngineProfiles::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([3], 1));
        $this->assertSame([[50, 1, null]], ForeignProfile::$pages);
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('rivals')]
    public function it_refuses_two_extensions_that_claim_one_engine_in_any_order(array $extensions): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => $extensions]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidEngineExtensionException::conflictingProfiles(ForeignEngine::class, VoucherResource::class, 'a', EarlyForeignEngineProfiles::class, 'b', RivalEngineProfiles::class));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    /** @return array<string, array{list<class-string>}> */
    public static function rivals(): array
    {
        return [
            'in name order' => [[EarlyForeignEngineProfiles::class, RivalEngineProfiles::class]],
            'in reverse order' => [[RivalEngineProfiles::class, EarlyForeignEngineProfiles::class]],
        ];
    }

    #[Test]
    public function it_names_both_extensions_the_engine_and_the_ways_out(): void
    {
        $exception = InvalidEngineExtensionException::conflictingProfiles(ForeignEngine::class, VoucherResource::class, 'a', ForeignEngineProfiles::class, 'b', RivalEngineProfiles::class);

        $this->assertSame(
            'The [a] extension '.ForeignEngineProfiles::class.' and the [b] extension '.RivalEngineProfiles::class.' both provide the engine profile of ['.ForeignEngine::class.'] for '.VoucherResource::class.'. Let one of them provide it: return null from engineProfile() of the other for this engine, or add one of them to the [rest.disabled_extensions] config.',
            $exception->getMessage(),
        );
        $this->assertSame([true, false], [$exception->concerns(RivalEngineProfiles::class), $exception->concerns('b')]);
    }

    #[Test]
    public function it_checks_a_provided_profile_like_a_built_in_one(): void
    {
        $this->useEngine(new ForeignEngine);
        config(['rest.extensions' => [UnsortedForeignEngineProfiles::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidTextSearchException::unsortableEngine(VoucherResource::class, ForeignEngine::class));

        $this->search('vouchers', ['text' => ['value' => 'report']]);
    }

    #[Test]
    public function it_keeps_the_built_in_profile_when_no_extension_claims_the_engine(): void
    {
        config(['rest.extensions' => [ForeignEngineProfiles::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
        $this->assertSame([], ForeignProfile::$pages);
        $this->assertContains('ForeignEngineProfiles', EngineLog::$calls);
    }

    #[Test]
    public function it_gives_the_provider_the_resource_and_the_engine_only(): void
    {
        $engine = new ForeignEngine;
        $this->useEngine($engine);
        config(['rest.extensions' => [ForeignEngineProfiles::class]]);

        $this->search('vouchers', ['text' => ['value' => 'report']])->assertOk();

        $context = EngineLog::$contexts[0];
        $this->assertInstanceOf(EngineProfileContext::class, $context);
        $this->assertSame([VoucherResource::class, $engine], [$context->resource, $context->engine]);
        $this->assertSame(['resource', 'engine'], array_map(
            fn (ReflectionProperty $property): string => $property->getName(),
            (new ReflectionClass(EngineProfileContext::class))->getProperties(ReflectionProperty::IS_PUBLIC),
        ));
    }

    #[Test]
    public function it_skips_a_provider_that_does_not_apply(): void
    {
        config(['rest.extensions' => [UnappliedEngineProfiles::class]]);

        $response = $this->search('vouchers', ['text' => ['value' => 'report']]);

        $response->assertOk()->assertExactJson($this->page([5, 4, 1, 3], 4));
        $this->assertSame([], EngineLog::$calls);
        $this->assertSame([], ForeignProfile::$pages);
    }
}
