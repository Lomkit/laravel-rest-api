<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class ReviewedTicketResource extends TicketResource
{
    public static ActionTarget $target = ActionTarget::Search;

    #[\Override]
    public function actions(): array
    {
        return [
            ReviewTickets::make()->target(self::$target),
            ReviewAllowedTickets::make()
                ->target(self::$target)
                ->skipUnauthorized(),
            QueuedReviewTickets::make()->target(self::$target),
            QueuedReviewAllowedTickets::make()->target(self::$target),
            BatchedReviewTickets::make()->target(self::$target),
        ];
    }
}
