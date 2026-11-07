<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Support\Responsable;
use Illuminate\Http\JsonResponse;
use Lomkit\Rest\Exceptions\InvalidMetricException;
use Lomkit\Rest\Http\Requests\MetricRequest;
use Lomkit\Rest\Metrics\MetricNode;
use Lomkit\Rest\Metrics\MetricQuery;
use Lomkit\Rest\Metrics\MetricResult;
use Lomkit\Rest\Search\SearchContext;

final readonly class MetricResponse implements Responsable
{
    private function __construct(public string $json) {}

    public static function of(MetricRequest $request, Resource $resource): self
    {
        $node = $request->payload();

        if (! $node->metric->withoutSearching) {
            $resource->searching(SearchContext::of($node->search));
        }

        $store = $resource->context()->metricStore();

        return new self($store->remember($node, fn (): string => self::render($resource, $node)));
    }

    private static function render(Resource $resource, MetricNode $node): string
    {
        $metric = $node->metric;
        $text = $resource->context()->text($resource);
        $values = $text
            ->run(fn (): MetricResult => $metric->calculate(MetricQuery::of($resource, $node->search, $text)))
            ->jsonSerialize();

        if (array_key_exists('type', $values)) {
            throw InvalidMetricException::typeInResult($metric::class);
        }

        return json_encode(['type' => $metric->type(), ...$values], JSON_PRESERVE_ZERO_FRACTION | JSON_THROW_ON_ERROR);
    }

    #[\Override]
    public function toResponse($request): JsonResponse
    {
        return new JsonResponse('{"data":'.$this->json.'}', json: true);
    }
}
