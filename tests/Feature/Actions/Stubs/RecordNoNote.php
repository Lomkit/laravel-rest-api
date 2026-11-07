<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;

class RecordNoNote extends RecordNotes
{
    public ActionTarget $target = ActionTarget::None;
}
