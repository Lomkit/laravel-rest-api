<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Authorization\Denial;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

final readonly class DenialsRenderer
{
    private const int HIDDEN = 404;

    public function __invoke(HttpExceptionInterface $exception): ?JsonResponse
    {
        $denied = $exception->getPrevious();

        if (! $denied instanceof AuthorizationDenied || $exception->getStatusCode() === self::HIDDEN) {
            return null;
        }

        return new JsonResponse(
            ['message' => $exception->getMessage(), 'denials' => self::shown($denied)],
            $exception->getStatusCode(),
            $exception->getHeaders(),
        );
    }

    /** @return list<array{path: string, ability: string, key?: int|string, message: string}> */
    private static function shown(AuthorizationDenied $denied): array
    {
        $shown = array_filter($denied->denials, fn (Denial $denial): bool => $denial->decision->status !== self::HIDDEN);

        return array_values(array_map(fn (Denial $denial): array => $denial->toArray(), $shown));
    }
}
