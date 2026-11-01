<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Http\Requests\OperateRequest;

class RuledOperateRequest extends OperateRequest
{
    /** @var array<string, list<string>> */
    public static array $added = [];

    #[\Override]
    public function rules(): array
    {
        return [...parent::rules(), ...self::$added];
    }
}
