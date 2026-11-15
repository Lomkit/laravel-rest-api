<?php

namespace Lomkit\Rest\Tests\Unit\Exceptions;

use Lomkit\Rest\Exceptions\ExtensionException;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Exceptions\InvalidDocumentationExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidExtensionException;
use Lomkit\Rest\Exceptions\InvalidFieldExtensionException;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Exceptions\InvalidRouteExtensionException;
use Lomkit\Rest\Exceptions\InvalidWriteExtensionException;
use Lomkit\Rest\Fields\FieldReduction;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstAugmentation;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondAugmentation;
use Lomkit\Rest\Tests\Fixtures\Extensions\AbstractNotingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\BadlyNamedExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\KindlessExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotAnExtension;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExtensionExceptionTest extends TestCase
{
    private const string MISSING = 'Lomkit\Rest\Tests\Fixtures\Extensions\MissingExtension';

    #[Test]
    #[DataProvider('kindExceptions')]
    public function it_gives_a_kind_extension_exception_the_shared_base_only(ExtensionException $exception, string $class, string $name): void
    {
        $this->assertSame([true, false, true, false], [
            is_a($exception, ExtensionException::class),
            is_a($exception, InvalidExtensionException::class),
            $exception->concerns($class),
            $exception->concerns($name),
        ]);
    }

    #[Test]
    public function it_keeps_the_engine_query_refusal_as_the_previous_exception(): void
    {
        $reason = InvalidEngineQueryException::undeclaredAttribute('owner');

        $exception = InvalidEngineExtensionException::refusedEngineCondition('owner', 'App\Rest\OwnerExtension', 'App\Rest\PostResource', $reason);

        $this->assertSame([$reason, true, false], [$exception->getPrevious(), $exception->concerns('App\Rest\OwnerExtension'), $exception->concerns('owner')]);
    }

    #[Test]
    public function it_names_the_extension_of_every_extension_exception(): void
    {
        $query = InvalidQueryExtensionException::invalidColumn('first', FirstAugmentation::class, 'a b', 'App\Rest\PostResource');
        $registry = InvalidExtensionException::invalidName(BadlyNamedExtension::class, 'Acl');

        $this->assertSame([true, true, true, true, false, false], [
            is_a($query, ExtensionException::class),
            is_a($registry, ExtensionException::class),
            $query->concerns(FirstAugmentation::class),
            $registry->concerns(BadlyNamedExtension::class),
            $query->concerns('first'),
            $query->concerns(SecondAugmentation::class),
        ]);

        $refusals = [
            NotingExtension::class => [
                InvalidExtensionException::lateRegistration(NotingExtension::class),
                InvalidExtensionException::foreignBinding(NotingExtension::class, CountingExtension::class),
                InvalidExtensionException::instanceGiven('Rest::registerExtensions()', NotingExtension::class),
            ],
            self::MISSING => [InvalidExtensionException::notAClass(self::MISSING)],
            NotAnExtension::class => [InvalidExtensionException::notAnExtension(NotAnExtension::class)],
            KindlessExtension::class => [InvalidExtensionException::noKind(KindlessExtension::class), InvalidExtensionException::invalidName(KindlessExtension::class, 'Acl')],
            AbstractNotingExtension::class => [InvalidExtensionException::notInstantiable(AbstractNotingExtension::class)],
        ];

        foreach ($refusals as $class => $exceptions) {
            foreach ($exceptions as $exception) {
                $this->assertTrue($exception->concerns($class));
                $this->assertFalse($exception->concerns(CountingExtension::class));
                $this->assertStringContainsString($class, $exception->getMessage());
            }
        }

        $this->assertFalse(InvalidExtensionException::sharedName('acl', [NotingExtension::class, CountingExtension::class])->concerns(NotingExtension::class));
    }

    /** @return array<string, array{ExtensionException, string, string}> */
    public static function kindExceptions(): array
    {
        return [
            'engine' => [InvalidEngineExtensionException::repeatedIndexAttribute('first', 'App\Rest\FirstExtension', 'App\Rest\PostResource', 'owner_id'), 'App\Rest\FirstExtension', 'first'],
            'authorization' => [InvalidAuthorizationExtensionException::changedRows('acl', 'App\Rest\AclExtension', 'App\Rest\PostResource', 'view'), 'App\Rest\AclExtension', 'acl'],
            'field' => [InvalidFieldExtensionException::unknownField('mask', 'App\Rest\MaskExtension', 'App\Rest\PostResource', FieldReduction::Hide, 'salary'), 'App\Rest\MaskExtension', 'mask'],
            'route' => [InvalidRouteExtensionException::invalidMiddleware('plans', 'App\Rest\PlanRoutes', 'App\Rest\PostResource', Endpoint::Search, 'an empty string'), 'App\Rest\PlanRoutes', 'plans'],
            'write' => [InvalidWriteExtensionException::reservedRefusalStatus('quota', 'App\Rest\WriteQuota', 'App\Rest\PostResource', Endpoint::Mutate, 409), 'App\Rest\WriteQuota', 'quota'],
            'meta' => [InvalidMetaExtensionException::instructionsName('instructions', 'App\Rest\PlanMeta', 'App\Rest\PostResource'), 'App\Rest\PlanMeta', 'instructions'],
            'documentation' => [InvalidDocumentationExtensionException::statusOutOfRange('quota', 'App\Rest\QuotaDocs', 'api.posts.mutate', 302), 'App\Rest\QuotaDocs', 'quota'],
        ];
    }
}
