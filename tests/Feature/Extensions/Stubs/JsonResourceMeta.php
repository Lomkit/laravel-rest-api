<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Closure;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Pagination\LengthAwarePaginator;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

final class JsonResourceMeta
{
    /** @return array<string, array{Closure(): mixed, string, class-string}> */
    public static function cases(): array
    {
        $resource = fn (): JsonResource => new JsonResource(new Ticket(['title' => 'Printer jam']));

        return [
            'a resource of a model' => [fn (): array => ['ticket' => $resource()], 'ticket', JsonResource::class],
            'a resource collection' => [fn (): array => ['tickets' => JsonResource::collection(collect([new Ticket(['title' => 'Printer jam'])]))], 'tickets', AnonymousResourceCollection::class],
            'a resource inside a plain collection' => [fn (): array => ['tickets' => collect([$resource()])], 'tickets.0', JsonResource::class],
            'a resource inside a paginator' => [fn (): array => ['page' => new LengthAwarePaginator([$resource()], 1, 10)], 'page.0', JsonResource::class],
            'a resource inside a cursor paginator' => [fn (): array => ['page' => new CursorPaginator([$resource()], 10)], 'page.0', JsonResource::class],
            'a resource collection inside a plain collection' => [fn (): array => ['lists' => collect(['open' => JsonResource::collection(collect([new Ticket(['title' => 'Printer jam'])]))])], 'lists.open', AnonymousResourceCollection::class],
            'a resource nested deep' => [fn (): array => ['a' => ['b' => (object) ['c' => [$resource()]]]], 'a.b.c.0', JsonResource::class],
        ];
    }
}
