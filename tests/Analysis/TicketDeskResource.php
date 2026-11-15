<?php

namespace Lomkit\Rest\Tests\Analysis;

use DateTimeInterface;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Authorization\RowVersions;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

final class TicketDeskResource extends Resource
{
    public static string $model = Ticket::class;

    #[\Override]
    public function actions(): array
    {
        return [CloseTicketsAction::class, DetachTicketTagsAction::make()];
    }

    #[\Override]
    public function instructions(): array
    {
        return [RankTicketsInstruction::class, TaggedTicketsInstruction::make()];
    }

    #[\Override]
    public function prepareAuthorization(PendingChecks $checks): void
    {
        $checks->rows->loadMissing('team');
    }

    #[\Override]
    public function versionAuthorization(RowVersions $versions): void
    {
        foreach ($versions->rows as $row) {
            $updatedAt = $row->getAttribute('team_updated_at');

            if ($updatedAt instanceof DateTimeInterface) {
                $versions->add($row, 'team', $updatedAt);
            }
        }
    }
}
