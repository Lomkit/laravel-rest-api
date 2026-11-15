<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Extensions\Contexts\DetailsMetaContext;
use Lomkit\Rest\Extensions\Contexts\SearchMetaContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsDetailsMeta;
use Lomkit\Rest\Extensions\Kinds\AddsSearchMeta;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class QuotaMeta extends Extension implements AddsDetailsMeta, AddsSearchMeta
{
    #[\Override]
    public static function name(): string
    {
        return 'quota';
    }

    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return is_a($resource, TicketResource::class, true);
    }

    #[\Override]
    public function searchMeta(SearchMetaContext $context): array
    {
        MetaLog::$search[] = $context;

        return [
            'remaining' => 100 - $context->total,
            'titles' => array_map(fn (Model $row): mixed => $row->getAttribute('title'), $context->rows),
            'user' => $context->user?->getAuthIdentifier(),
        ];
    }

    #[\Override]
    public function detailsMeta(DetailsMetaContext $context): array
    {
        MetaLog::$details[] = $context;

        return ['plan' => 'pro', 'user' => $context->user?->getAuthIdentifier()];
    }
}
