<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\MorphToMany;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\GuestTicketBacklog;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\MetricTicketResource;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\NewestFirst;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketBacklog;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\TicketsByStatus;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\LateCloseTickets;
use Lomkit\Rest\Tests\Feature\Routing\Stubs\ThrottledWatchTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\CloseTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\RefreshTickets;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\ReportOutage;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;

class DeskTicketResource extends MetricTicketResource
{
    public static bool $byClass = true;

    public static bool $dropped = false;

    public static bool $lateAction = false;

    /** @var list<Action|class-string<Action>> */
    public static array $moreActions = [];

    /** @var list<Metric|class-string<Metric>> */
    public static array $moreMetrics = [];

    public static bool $instructionsByClass = true;

    /** @var list<Instruction|class-string<Instruction>> */
    public static array $moreInstructions = [];

    #[\Override]
    public function relations(): array
    {
        return [
            BelongsTo::make('team', TeamResource::class),
            BelongsToMany::make('tags', DeskTagResource::class)
                ->pivotFields([Integer::make('position')->nullable()])
                ->abilities(attach: 'attachTag', detach: 'detachTag'),
            MorphToMany::make('labels', TagResource::class)
                ->pivotFields([Integer::make('position')->nullable()])
                ->abilities(attach: 'attachTag', detach: 'detachTag'),
        ];
    }

    #[\Override]
    public function actions(): array
    {
        if (! self::$byClass) {
            return [
                CloseTickets::make()->middleware('throttle:1,1'),
                RefreshTickets::make(),
                ReportOutage::make()->withoutMiddleware(['auth', 'verified']),
                ThrottledWatchTickets::make(),
                ...self::$moreActions,
            ];
        }

        return [
            DeskCloseTickets::class,
            ...(self::$dropped ? [] : [DeskRefreshTickets::class]),
            DeskReportOutage::class,
            ThrottledWatchTickets::class,
            ...(self::$lateAction ? [LateCloseTickets::class] : []),
            ...self::$moreActions,
        ];
    }

    #[\Override]
    public function metrics(): array
    {
        if (! self::$byClass) {
            return [
                TicketBacklog::make()->middleware('throttle:60,1'),
                TicketsByStatus::make(),
                GuestTicketBacklog::make()->withoutMiddleware('auth'),
                ...self::$moreMetrics,
            ];
        }

        return [DeskTicketBacklog::class, DeskTicketsByStatus::class, DeskGuestTicketBacklog::class, ...self::$moreMetrics];
    }

    #[\Override]
    public function instructions(): array
    {
        if (! self::$instructionsByClass) {
            return [NewestFirst::make(), new DeskTitleMatch(new TicketJournal), ...self::$moreInstructions];
        }

        return [NewestFirst::make(), DeskTitleMatch::class, ...self::$moreInstructions];
    }

    public static function resetDeclarations(): void
    {
        self::$byClass = true;
        self::$dropped = false;
        self::$lateAction = false;
        self::$moreActions = [];
        self::$moreMetrics = [];
        self::$instructionsByClass = true;
        self::$moreInstructions = [];
        DeskTagResource::$broken = false;
        parent::reset();
    }
}
