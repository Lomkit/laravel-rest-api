<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Bus\PendingBatch;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

class ReviewTickets extends Action
{
    /** @var list<list<int>> */
    public static array $runs = [];

    public static ?string $ability = 'update';

    public static bool $batched = false;

    public static bool $recheck = false;

    public int $chunkCount = 2;

    public static function reset(): void
    {
        self::$runs = [];
        self::$ability = 'update';
        self::$batched = false;
        self::$recheck = false;
        ReviewedTicketResource::$target = ActionTarget::Search;
    }

    #[\Override]
    public function authorizeEach(): ?string
    {
        return self::$ability;
    }

    /** @param Collection<int, Ticket> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        self::$runs[] = $models->modelKeys();

        if (self::$recheck) {
            $models->each(fn (Ticket $ticket): bool => $this->resource->authorizedTo('update', $ticket)->allowed());
        }
    }

    #[\Override]
    public function withBatch(ActionFields $fields, PendingBatch $batch): void
    {
        self::$batched = true;
    }
}
