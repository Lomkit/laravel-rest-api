<?php

namespace Lomkit\Rest\Text\Engine;

use Throwable;

/** @internal */
interface EngineWalkRefusals
{
    public function beyondWindow(int $window): Throwable;

    public function changed(): Throwable;
}
