<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Lomkit\Rest\Tests\Fixtures\Helpdesk\TagResource;

class DeskTagResource extends TagResource
{
    public static bool $broken = false;

    #[\Override]
    public function instructions(): array
    {
        return [DeskTagMatch::class, ...(self::$broken ? [LabelledDeskInstruction::class] : [])];
    }
}
