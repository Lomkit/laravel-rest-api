<?php

namespace Lomkit\Rest\Tests\Unit\Concerns\Stubs;

use Lomkit\Rest\Actions\Action;

class LabelledAction extends Action
{
    public function __construct(public readonly string $label = 'Archive', public readonly int $priority = 1) {}
}
