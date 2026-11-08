<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;

final class VersionConflict extends HttpResponseException
{
    public const string MESSAGE = 'The record was changed by another request.';

    /** @param non-empty-list<array{path: string, key: int|string, version: mixed}> $conflicts */
    public function __construct(public readonly array $conflicts)
    {
        parent::__construct(new JsonResponse(['message' => self::MESSAGE, 'conflicts' => $conflicts], JsonResponse::HTTP_CONFLICT));

        $this->message = self::MESSAGE;
    }
}
