<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

class DeskCloseTickets extends CloseTickets
{
    public ?string $name = 'Close Tickets';

    public array $middleware = ['throttle:1,1'];

    public function __construct(private readonly TicketJournal $journal)
    {
        DeclarationLog::$built[] = static::class;
    }

    /** @param Collection<int, Ticket> $models */
    #[\Override]
    public function handle(ActionFields $fields, Collection $models): void
    {
        $this->journal->write('closed '.$models->count());

        parent::handle($fields, $models);
    }
}
