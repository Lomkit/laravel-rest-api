<?php

namespace Lomkit\Rest\Tests\Analysis;

use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Contexts\MetaDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\OperationDocumentationContext;
use Lomkit\Rest\Extensions\Contexts\ResourceCheckContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Extensions\Kinds\ChecksResources;
use Lomkit\Rest\Extensions\Kinds\DocumentsExtension;
use Lomkit\Rest\Http\Routing\Endpoint;

final class TicketResponsesExtension extends Extension implements AddsDetailsMeta, AddsSearchMeta, ChecksResources, DocumentsExtension
{
    #[\Override]
    public static function name(): string
    {
        return 'ticket_responses';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TicketDeskResource::class;
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        $closed = array_filter($context->rows, fn (Model $row): bool => $row->getAttribute('status') === TicketStatus::Closed->value);

        return [
            'closed' => count($closed),
            'remaining' => max(0, 1000 - $context->total),
            'page' => [$context->page, $context->perPage],
            'filtered' => $context->search->filtersOn('status'),
            'desk' => $context->resource::class,
            'user' => $context->user?->getAuthIdentifier(),
        ];
    }

    #[\Override]
    public function detailsMeta(DetailsMetaContext $context): array
    {
        return ['desk' => $context->resource::class, 'signed_in' => $context->user !== null];
    }

    #[\Override]
    public function documentOperation(OperationDocumentationContext $context): void
    {
        if ($context->endpoint !== Endpoint::Mutate && ! $context->declaration instanceof Action) {
            return;
        }

        if (! $context->documents(423) && $context->authenticates && $context->resource === TicketDeskResource::class) {
            $message = new ObjectType;
            $message->addProperty('message', new StringType);
            $message->setRequired(['message']);
            $context->response(423, 'Frozen', 'Tickets are frozen', $message);
        }
    }

    #[\Override]
    public function documentMeta(MetaDocumentationContext $context): void
    {
        $search = new ObjectType;
        $search->addProperty('closed', new IntegerType);
        $search->addProperty('remaining', new IntegerType);
        $context->searchMeta($search);

        $details = new ObjectType;
        $details->addProperty('signed_in', new BooleanType);
        $context->detailsMeta($details->setDescription("The desk of {$context->resource}"));
    }

    #[\Override]
    public function checkResource(ResourceCheckContext $context): void
    {
        $table = $context->resource::newModel()->getTable();

        if (! Schema::hasColumn($table, 'status')) {
            $context->problem("the {$table} table has no status column.");
        }
    }
}
