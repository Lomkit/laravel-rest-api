<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\OperationNode;
use Lomkit\Rest\Http\Requests\OperateRequest;

class PayloadReadingOperateRequest extends OperateRequest
{
    public static ?OperationNode $read = null;

    /** @var array<string, mixed>|null */
    public static ?array $validated = null;

    #[\Override]
    protected function passedValidation(): void
    {
        parent::passedValidation();

        self::$read = $this->payload();
        self::$validated = $this->validated();
    }
}
