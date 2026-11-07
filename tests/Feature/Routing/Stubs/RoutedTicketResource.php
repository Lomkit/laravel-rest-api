<?php

namespace Lomkit\Rest\Tests\Feature\Routing\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\RefreshTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ReportOutage;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;

class RoutedTicketResource extends TicketResource
{
    public static bool $late = false;

    public static bool $dropped = false;

    public static bool $duplicated = false;

    #[\Override]
    public function actions(): array
    {
        return [
            CloseTickets::make()->middleware('throttle:1,1'),
            ...(self::$dropped ? [] : [RefreshTickets::make()]),
            ReportOutage::make()
                ->withoutMiddleware('auth')
                ->withoutMiddleware(['verified']),
            ThrottledWatchTickets::make(),
            ...(self::$late ? [LateCloseTickets::make()] : []),
            ...(self::$duplicated ? [CloseTickets::make()] : []),
        ];
    }

    public static function reset(): void
    {
        self::$late = false;
        self::$dropped = false;
        self::$duplicated = false;
    }
}
