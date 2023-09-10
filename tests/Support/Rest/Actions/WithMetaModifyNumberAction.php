<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

class WithMetaModifyNumberAction extends ModifyNumberAction
{
    public function __construct()
    {
        $this->withMeta([
            'color' => '#FFFFFF',
        ]);
    }
}
