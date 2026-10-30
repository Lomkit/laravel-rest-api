<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final readonly class DenialsRenderer
{
    public function __invoke(HttpExceptionInterface $exception): ?JsonResponse
    {
        $denied = $exception->getPrevious();

        if (! $denied instanceof AuthorizationDenied || $exception->getStatusCode() === 404) {
            return null;
        }

        return new JsonResponse(
            ['message' => $exception->getMessage(), 'denials' => $denied->toArray()],
            $exception->getStatusCode(),
            $exception->getHeaders(),
        );
    }
}
