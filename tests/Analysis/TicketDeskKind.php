<?php

namespace Lomkit\Rest\Tests\Analysis;

use Lomkit\Rest\Extensions\Kinds\ExtensionKind;

interface TicketDeskKind extends ExtensionKind
{
    public function label(): string;
}
