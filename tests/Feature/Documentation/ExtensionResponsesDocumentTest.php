<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Lomkit\Rest\Exceptions\InvalidDocumentationException;
use Lomkit\Rest\Exceptions\InvalidDocumentationExtensionException;
use Lomkit\Rest\Extensions\Applicability;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ArchivedResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CheckedResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CreatedResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DottedResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ForbiddenResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RivalQuotaResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ShiftingResponses;
use Lomkit\Rest\Tests\Fixtures\Extensions\CountingExtension;
use Lomkit\Rest\Tests\Fixtures\Festival\ArchiveShows;
use Lomkit\Rest\Tests\Fixtures\Festival\ShowResource;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ExtensionResponsesDocumentTest extends DocumentationTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MetaLog::reset();
        Applicability::flush();
    }

    #[\Override]
    protected function tearDown(): void
    {
        MetaLog::reset();
        Applicability::flush();

        parent::tearDown();
    }

    #[Test]
    public function it_adds_the_response_of_an_extension_to_an_operation(): void
    {
        config(['rest.extensions' => ['quota' => QuotaResponses::class]]);

        $document = $this->document();

        $search = $document['paths']['/stages/search']['post']['responses'];
        $this->assertSame([200, 403, 404, 422, 429], array_keys($search));
        $this->assertSame(['$ref' => '#/components/responses/quota.Exceeded'], $search[429]);
        $this->assertSame([200, 403, 404, 409, 422, 429], array_keys($document['paths']['/stages/mutate']['post']['responses']));
        $this->assertSame([200, 403, 404, 422], array_keys($document['paths']['/stages']['delete']['responses']));
        $this->assertSame(
            '{"description":"The monthly quota is reached","content":{"application\/json":{"schema":{"type":"object","properties":{"message":{"type":"string"}},"required":["message"]}}}}',
            json_encode($document['components']['responses']['quota.Exceeded']),
        );
        $this->assertSame(['quota.Exceeded'], array_values(array_filter(array_keys($document['components']['responses']), fn (string $name): bool => str_starts_with($name, 'quota.'))));
    }

    /** @return array<string, array{array<string, class-string>, array<string, class-string>}> */
    public static function orders(): array
    {
        $quota = ['quota' => QuotaResponses::class, 'archived' => ArchivedResponses::class];

        return [
            'quota first' => [$quota, array_reverse($quota)],
            'archived first' => [array_reverse($quota), $quota],
        ];
    }

    /**
     * @param  array<string, class-string>  $extensions
     * @param  array<string, class-string>  $reversed
     */
    #[Test]
    #[DataProvider('orders')]
    public function it_sorts_the_responses_by_status_whatever_the_config_order(array $extensions, array $reversed): void
    {
        config(['rest.extensions' => $extensions]);
        $first = $this->document();
        config(['rest.extensions' => $reversed]);
        $second = $this->document();

        $this->assertSame([200, 403, 404, 409, 410, 422, 429], array_keys($first['paths']['/stages/mutate']['post']['responses']));
        $this->assertSame(json_encode($first), json_encode($second));
    }

    #[Test]
    public function it_refuses_a_status_that_the_package_documents(): void
    {
        config(['rest.extensions' => ['forbidden' => ForbiddenResponses::class], 'app.debug' => false]);

        $this->expectExceptionObject(InvalidDocumentationExtensionException::statusTaken('forbidden', ForbiddenResponses::class, 'api/stages/search', 403, 'the package'));

        $this->document();
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function rivals(): array
    {
        return [
            'quota first' => [['quota' => QuotaResponses::class, 'rival' => RivalQuotaResponses::class]],
            'rival first' => [['rival' => RivalQuotaResponses::class, 'quota' => QuotaResponses::class]],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('rivals')]
    public function it_refuses_a_status_that_another_extension_documents(array $extensions): void
    {
        config(['rest.extensions' => $extensions, 'app.debug' => false]);

        $this->expectExceptionObject(InvalidDocumentationExtensionException::statusTaken('rival', RivalQuotaResponses::class, 'api/stages/search', 429, 'the [quota] extension '.QuotaResponses::class));

        $this->document();
    }

    #[Test]
    public function it_refuses_a_status_outside_the_error_statuses(): void
    {
        config(['rest.extensions' => ['created' => CreatedResponses::class], 'app.debug' => false]);

        $this->expectExceptionObject(InvalidDocumentationExtensionException::statusOutOfRange('created', CreatedResponses::class, 'api/stages/search', 201));

        $this->document();
    }

    #[Test]
    public function it_refuses_a_response_name_that_is_not_letters_and_digits(): void
    {
        config(['rest.extensions' => ['dotted' => DottedResponses::class], 'app.debug' => false]);

        $this->expectExceptionObject(InvalidDocumentationExtensionException::invalidResponseName('dotted', DottedResponses::class, 'quota.reached'));

        $this->document();
    }

    #[Test]
    public function it_refuses_one_response_name_with_two_contents(): void
    {
        config(['rest.extensions' => ['shifting' => ShiftingResponses::class], 'app.debug' => false]);

        $this->expectExceptionObject(InvalidDocumentationException::componentTaken('response', 'shifting.Exceeded', ShiftingResponses::class, ShiftingResponses::class));

        $this->document();
    }

    #[Test]
    public function it_tells_whether_the_operation_documents_a_status(): void
    {
        config(['rest.extensions' => ['checked' => CheckedResponses::class]]);

        $this->document();

        $this->assertContains([false, true], MetaLog::$documented);
        $this->assertContains([true, true], MetaLog::$documented);
        $this->assertNotContains([true, false], MetaLog::$documented);
    }

    #[Test]
    public function it_passes_the_operation_to_the_extension(): void
    {
        config(['rest.extensions' => ['recording' => RecordingResponses::class]]);

        $document = $this->document();

        $archive = $this->recorded(ShowResource::class, Endpoint::Operate, ArchiveShows::class);
        $this->assertInstanceOf(ArchiveShows::class, $archive->declaration);
        $this->assertTrue($archive->authenticates);
        $search = $this->recorded(StageResource::class, Endpoint::Search, null);
        $this->assertNull($search->declaration);
        $this->assertFalse($search->authenticates);
        $operations = array_sum(array_map(fn (array $methods): int => count(array_filter(array_keys($methods), fn (string $method): bool => in_array($method, ['get', 'post', 'put', 'patch', 'delete'], true))), $document['paths']));
        $this->assertCount($operations, MetaLog::$operations);
    }

    #[Test]
    public function it_keeps_the_document_without_a_documenting_extension(): void
    {
        config(['rest.extensions' => []]);
        $without = json_encode($this->document());
        config(['rest.extensions' => ['counting' => CountingExtension::class]]);

        $this->assertSame($without, json_encode($this->document()));
    }

    #[Test]
    public function it_documents_a_valid_openapi_document_with_extension_responses(): void
    {
        config(['rest.extensions' => ['quota' => QuotaResponses::class, 'archived' => ArchivedResponses::class]]);

        $this->assertSame([], (new OpenApiValidator)->documentErrors($this->document()));
    }

    /** @param class-string|null $declaration */
    private function recorded(string $resource, Endpoint $endpoint, ?string $declaration): OperationDocumentationContext
    {
        foreach (MetaLog::$operations as $context) {
            if ($context->resource === $resource && $context->endpoint === $endpoint && ($declaration === null || $context->declaration instanceof $declaration)) {
                return $context;
            }
        }

        $this->fail("No operation of {$resource} {$endpoint->value} was documented.");
    }
}
