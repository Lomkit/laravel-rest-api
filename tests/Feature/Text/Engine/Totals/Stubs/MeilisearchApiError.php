<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Totals\Stubs;

use RuntimeException;

class MeilisearchApiError extends RuntimeException
{
    public function __construct(string $message, public ?string $errorCode = null)
    {
        parent::__construct($message);
    }
}
