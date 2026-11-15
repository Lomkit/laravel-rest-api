<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Extensions\Contexts\MetaDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\DocumentsExtension;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

abstract class DocumentedResponses extends Extension implements DocumentsExtension
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === StageResource::class;
    }

    #[\Override]
    public function documentMeta(MetaDocumentationContext $context): void {}

    protected static function message(): ObjectType
    {
        return Schemas::required(['message' => new StringType]);
    }

    /** @param list<array{list<Endpoint>, int, string, string}> $responses */
    protected static function document(OperationDocumentationContext $context, array $responses): void
    {
        foreach ($responses as [$endpoints, $status, $name, $description]) {
            if (in_array($context->endpoint, $endpoints, true)) {
                $context->response($status, $name, $description, self::message());
            }
        }
    }
}
