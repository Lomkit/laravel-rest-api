<?php

namespace Lomkit\Rest\Http\Requests\Traits;

use Lomkit\Rest\Validation\Utf8Input;

trait ChecksUtf8Input
{
    private ?Utf8Input $utf8Input = null;

    protected function utf8Input(): Utf8Input
    {
        return $this->utf8Input ??= Utf8Input::of($this->all());
    }
}
