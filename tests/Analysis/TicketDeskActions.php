<?php

namespace Lomkit\Rest\Tests\Analysis;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Resource;

final readonly class TicketDeskActions
{
    /** @return list<string> */
    public function keys(Resource $resource): array
    {
        return array_values(array_map(fn (Action $action): string => $action->uriKey(), $resource->definition()->actions()));
    }

    public function closes(Resource $resource): bool
    {
        return $resource->definition()->actionExists('close-tickets') && $resource->definition()->action('close-tickets')->destructive;
    }
}
