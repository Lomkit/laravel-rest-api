<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Search\SearchNode;

class PayloadReadingSearchRequest extends SearchRequest
{
    public static ?SearchNode $read = null;

    #[\Override]
    protected function passedValidation(): void
    {
        parent::passedValidation();

        self::$read = $this->payload();
    }
}
