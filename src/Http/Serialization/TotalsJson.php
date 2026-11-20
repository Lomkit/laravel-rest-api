<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Http\JsonResponse;

/** @internal */
final readonly class TotalsJson
{
    /** @param array<array-key, mixed> $body */
    public static function response(array $body): JsonResponse
    {
        if (! is_array($body['totals'] ?? null)) {
            return new JsonResponse($body);
        }

        $placeholder = 'rest-totals-'.bin2hex(random_bytes(16));
        $response = new JsonResponse([...$body, 'totals' => $placeholder]);
        $totals = json_encode($body['totals'], $response->getEncodingOptions() | JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);

        return $response->setJson(str_replace('"'.$placeholder.'"', $totals, (string) $response->getContent()));
    }
}
