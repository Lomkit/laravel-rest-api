<?php

namespace Lomkit\Rest\Tests\Support\Rest\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Requests\RestRequest;

class WithMetaModifyNumberAction extends ModifyNumberAction
{
    public function __construct()
    {
        $this->withMeta([
            'color' => '#FFFFFF'
        ]);
    }
}