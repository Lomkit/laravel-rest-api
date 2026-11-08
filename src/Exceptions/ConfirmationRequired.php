<?php

namespace Lomkit\Rest\Exceptions;

use Carbon\CarbonImmutable;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Confirmation\Confirmation;

final class ConfirmationRequired extends HttpResponseException
{
    public function __construct(
        public readonly Confirmation $confirmation,
        public readonly string $token,
        public readonly CarbonImmutable $expiresAt,
    ) {
        parent::__construct(new JsonResponse([
            'message' => $confirmation->message,
            'confirmation' => [
                'token' => $token,
                'expires_at' => $expiresAt->toIso8601ZuluString(),
                'data' => (object) $confirmation->data,
            ],
        ], 428));
    }
}
