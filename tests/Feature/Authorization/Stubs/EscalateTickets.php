<?php

namespace Lomkit\Rest\Tests\Feature\Authorization\Stubs;

use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionFields;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;

class EscalateTickets extends Action
{
    /** @var list<list<int>> */
    public static array $runs = [];

    public static ?string $ability = 'update';

    public int $chunkCount = 2;

    public static function reset(): void
    {
        self::$runs = [];
        self::$ability = 'update';
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
    }
}
