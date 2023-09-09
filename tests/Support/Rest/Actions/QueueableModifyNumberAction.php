<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;

class QueueableModifyNumberAction extends ModifyNumberAction implements ShouldQueue
{
    //
}
