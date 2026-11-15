<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Fluent;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

final class WrappedMeta
{
    /** @return array<string, array{Closure(): mixed, string, class-string}> */
    public static function cases(): array
    {
        $ticket = fn (): Ticket => new Ticket(['title' => 'Printer jam']);

        return [
            'a resource in a Fluent' => [fn (): array => ['wrapper' => new Fluent(['ticket' => new JsonResource($ticket())])], 'wrapper.ticket', JsonResource::class],
            'a model in a Fluent' => [fn (): array => ['wrapper' => new Fluent(['ticket' => $ticket()])], 'wrapper.ticket', Ticket::class],
            'a model in a list of a Fluent' => [fn (): array => ['wrapper' => new Fluent(['tickets' => [$ticket()]])], 'wrapper.tickets.0', Ticket::class],
            'an Eloquent collection in a DTO' => [fn (): array => ['dto' => new MetaDto(new EloquentCollection([$ticket()]))], 'dto.data', EloquentCollection::class],
            'a resource in a DTO in a DTO' => [fn (): array => ['dto' => new MetaDto(new MetaDto([new JsonResource($ticket())]))], 'dto.data.data.0', JsonResource::class],
        ];
    }
}
