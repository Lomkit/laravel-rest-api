<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Support\Translation;

final class SyncConflict extends HttpResponseException
{
    public function __construct(public readonly string $path, public readonly int|string $key)
    {
        $message = Translation::line('rest::errors.sync_conflict');

        parent::__construct(new JsonResponse(['message' => $message, 'conflicts' => [['path' => $path, 'key' => $key]]], JsonResponse::HTTP_CONFLICT));

        $this->message = $message;
    }
}
