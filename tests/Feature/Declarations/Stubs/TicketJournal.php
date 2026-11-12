<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

final class TicketJournal
{
    public function write(string $entry): void
    {
        DeclarationLog::$journal[] = $entry;
    }
}
