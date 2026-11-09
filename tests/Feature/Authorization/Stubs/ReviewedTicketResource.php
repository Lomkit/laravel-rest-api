<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class ReviewedTicketResource extends TicketResource
{
    public static ActionTarget $target = ActionTarget::Search;

    public static ?int $maxResources = 1000;

    #[\Override]
    public function actions(): array
    {
        return [
            ReviewTickets::make()
                ->target(self::$target)
                ->maxResources(self::$maxResources),
            ReviewAllowedTickets::make()
                ->target(self::$target)
                ->skipUnauthorized(),
            QueuedReviewTickets::make()->target(self::$target),
            QueuedReviewAllowedTickets::make()->target(self::$target),
            BatchedReviewTickets::make()->target(self::$target),
            ReviewTicketsPerRow::make()
                ->target(self::$target)
                ->transactionPerRow(),
            ReviewAllowedTicketsPerRow::make()
                ->target(self::$target)
                ->transactionPerRow()
                ->skipUnauthorized(),
            QueuedReviewAllowedTicketsPerRow::make()
                ->target(self::$target)
                ->transactionPerRow()
                ->skipUnauthorized(),
        ];
    }
}
