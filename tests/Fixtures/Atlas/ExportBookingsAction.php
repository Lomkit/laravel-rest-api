<?php

namespace Lomkit\Rest\Tests\Fixtures\Atlas;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;

class ExportBookingsAction extends Action
{
    public ActionTarget $target = ActionTarget::Resources;
}
