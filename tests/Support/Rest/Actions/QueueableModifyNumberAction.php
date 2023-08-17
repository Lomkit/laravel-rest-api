<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

class QueueableModifyNumberAction extends ModifyNumberAction implements ShouldQueue
{
    //
}