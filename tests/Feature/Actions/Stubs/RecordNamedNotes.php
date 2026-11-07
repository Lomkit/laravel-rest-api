<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;

class RecordNamedNotes extends RecordNotes
{
    public ActionTarget $target = ActionTarget::Resources;
}
