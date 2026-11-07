<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Http\Requests\MetricRequest;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricQuery;
use Lomkit\Rest\Metrics\MetricResult;
use Lomkit\Rest\Search\SearchContext;

final readonly class MetricResponse implements Responsable
{
    /** @param array<string, mixed> $data */
    private function __construct(public array $data) {}

    public static function of(MetricRequest $request, Resource $resource): self
    {
        $node = $request->payload();
        $metric = $node->metric;

        if (! $metric->withoutSearching) {
            $resource->searching(SearchContext::of($node->search));
        }

        $text = $resource->context()->text($resource);
        $result = $text->run(fn (): MetricResult => $metric->calculate(MetricQuery::of($resource, $node->search, $text)));

        return new self(self::data($metric, $result));
    }

    /** @return array<string, mixed> */
    private static function data(Metric $metric, MetricResult $result): array
    {
        $values = $result->jsonSerialize();

        if (array_key_exists('type', $values)) {
            throw InvalidMetricException::typeInResult($metric::class);
        }

        return ['type' => $metric->type(), ...$values];
    }

    #[\Override]
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse(['data' => $this->data], options: JSON_PRESERVE_ZERO_FRACTION);
    }
}
