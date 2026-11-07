<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Routing\Route;
use LogicException;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Search\SearchParser;
use Lomkit\Rest\Validation\Search\SearchShape;
use Lomkit\Rest\Validation\ValidationContext;
use Symfony\Component\HttpKernel\Exception\HttpException;

class MetricRequest extends RestRequest
{
    private ?MetricNode $node = null;

    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Metric;
    }

    #[\Override]
    protected function authorizeEntry(Authorizer $authorizer): void
    {
        $resource = $this->rootResource();
        $metric = $this->metric($resource);

        if ($metric === null) {
            $authorizer->plan([Check::onClass($resource, 'viewAny')]);

            throw new HttpException(404);
        }

        $abilities = $metric->abilities();

        if ($abilities !== []) {
            $authorizer->plan([Check::onClass($resource, 'viewAny')]);
        }

        $authorizer->enter($resource, self::checksOn($resource, $abilities === [] ? [] : ['viewAny', ...$resource->abilities(Endpoint::Metric), ...$abilities]));
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        $this->resource($resource = $this->rootResource());

        $this->node ??= $this->parse($resource);

        return $this->parsedRules()->rules();
    }

    public function payload(): MetricNode
    {
        $rules = $this->parsedRules();

        if ($this->node === null || ! $rules->violations()->isEmpty() || $rules->isPartial() || ! $this->hasPassedValidation()) {
            throw new LogicException('The metric was read before the request passed validation.');
        }

        return $this->node;
    }

    public function metricKey(): ?string
    {
        $route = $this->route();
        $key = $route instanceof Route ? $route->defaults['metric'] ?? null : null;

        return is_string($key) ? $key : null;
    }

    private function metric(Resource $resource): ?Metric
    {
        $key = $this->metricKey();

        return $key === null ? null : $resource->definition()->metric($key);
    }

    private function parse(Resource $resource): MetricNode
    {
        $metric = $this->metric($resource) ?? throw new HttpException(404);
        $rules = $this->parsedRules();
        $text = $resource->definition()->text();
        $context = ValidationContext::root($resource, $this->budget(), $rules, $text->sqlGrammar());
        $search = (new SearchParser(SearchShape::Aggregate))->parse(Cursor::of($this->all(), $rules->violations())->at('search'), $context);

        return new MetricNode($metric, $search);
    }
}
