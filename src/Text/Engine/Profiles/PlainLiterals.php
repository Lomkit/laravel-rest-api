<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Lomkit\Rest\Text\Engine\EngineLiteral;
use Lomkit\Rest\Text\Engine\PlainLiteral;

trait PlainLiterals
{
    #[\Override]
    public function literal(): EngineLiteral
    {
        return new PlainLiteral;
    }
}
