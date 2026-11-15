<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class WriteRefused extends HttpResponseException
{
    public function __construct(public readonly string $extension, string $message, int $status)
    {
        parent::__construct(new JsonResponse(['message' => $message], $status));

        $this->message = $message;
    }
}
