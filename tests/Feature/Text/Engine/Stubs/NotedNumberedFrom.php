<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Tests\Fixtures\Ledger\NumberedFrom;

class NotedNumberedFrom extends NumberedFrom
{
    #[\Override]
    public function searchMeta(InstructionFields $fields): array
    {
        return ['min' => $fields->min];
    }
}
