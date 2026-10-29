<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

class ConflictingStateModifyNumberAction extends ModifyNumberAction
{
    public bool $standalone = true;

    public bool $targeted = true;
}
