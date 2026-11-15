<?php

namespace Lomkit\Rest\Tests\Analysis;

use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Extensions\Contexts\RouteMiddlewareContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\AddsRouteMiddleware;
use Lomkit\Rest\Extensions\Kinds\NarrowsFields;
use Lomkit\Rest\Http\Routing\Endpoint;

final class TicketFieldsExtension extends Extension implements AddsRouteMiddleware, NarrowsFields
{
    #[\Override]
    public function appliesTo(string $resource): bool
    {
        return $resource === TicketDeskResource::class;
    }

    #[\Override]
    public function narrowFields(FieldsContext $context): void
    {
        if ($context->user !== null || ! $context->declares('title')) {
            return;
        }

        $context->hide('title');
        $context->readOnly('status');
        $context->unfilterable('status', 'id');
        $context->unsortable('status');
    }

    #[\Override]
    public function routeMiddleware(RouteMiddlewareContext $context): array
    {
        if ($context->endpoint !== Endpoint::Search || ! is_a($context->resource, TicketDeskResource::class, true)) {
            return [];
        }

        return ['throttle:tickets', 'log:'.$context->routeName];
    }
}
