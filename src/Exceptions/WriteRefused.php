<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class WriteRefused extends HttpResponseException
{
    public function __construct(public readonly string $extension, public readonly string $reason, int $status)
    {
        parent::__construct(new JsonResponse(['message' => $reason], $status));

        $this->message = "The [{$extension}] extension refused the write.";
    }
}
