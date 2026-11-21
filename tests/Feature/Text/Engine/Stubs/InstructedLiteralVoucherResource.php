<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Stubs;

class InstructedLiteralVoucherResource extends LiteralVoucherResource
{
    #[\Override]
    public function instructions(): array
    {
        return [...parent::instructions(), CodeAmong::make(), CodeEquals::make()];
    }
}
