<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;

class RecountTripsAction extends Action
{
    public ActionTarget $target = ActionTarget::None;
}
