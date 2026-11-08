<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Support\Translation;

final class VersionConflict extends HttpResponseException
{
    /** @param non-empty-list<array{path: string, key: int|string, version: mixed}> $conflicts */
    public function __construct(public readonly array $conflicts)
    {
        $message = Translation::line('rest::errors.version_conflict');

        parent::__construct(new JsonResponse(['message' => $message, 'conflicts' => $conflicts], JsonResponse::HTTP_CONFLICT));

        $this->message = $message;
    }
}
