<?php

namespace Lomkit\Rest\Http\Requests;

use DateTimeZone;
use Illuminate\Routing\Route;
use Illuminate\Validation\Rule;
use LogicException;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\DeclarationKind;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Http\Routing\RouteFingerprint;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Metrics\MetricNode;
use Lomkit\Rest\Metrics\MetricRange;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ParsedRules;
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

        if ($metric->abilities() !== []) {
            $authorizer->plan([Check::onClass($resource, 'viewAny')]);
        }

        $authorizer->enter($resource, self::checksOn($resource, $metric->entryAbilities()));
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
        $metric = $key === null ? null : $resource->definition()->metric($key);

        if ($metric === null) {
            return null;
        }

        RouteFingerprint::check(RouteFingerprint::onRoute($this->route()), $metric, DeclarationKind::Metric);

        return $metric;
    }

    private function parse(Resource $resource): MetricNode
    {
        $metric = $this->metric($resource) ?? throw new HttpException(404);
        $rules = $this->parsedRules();
        $text = $resource->definition()->text();
        $context = ValidationContext::root($resource, $this->budget(), $rules, $text->sqlGrammar());
        $search = (new SearchParser(SearchShape::Aggregate))->parse(Cursor::of($this->all(), $rules->violations())->at('search'), $context);

        $reached = array_map(fn (Check $check): Resource => $check->resource, $rules->checks());

        return new MetricNode($metric, $search, $this->range($metric, $rules), $this->timezone($rules), $reached);
    }

    private function range(Metric $metric, ParsedRules $rules): ?MetricRange
    {
        $ranges = MetricRange::listOf($metric->ranges(), $metric::class);
        $values = array_map(fn (MetricRange $range): int => $range->value, $ranges);
        $sent = $this->input('range');
        $rules->add('range', $ranges === [] ? ['prohibited'] : [Rule::in($values)]);

        return array_find($ranges, fn (MetricRange $range): bool => is_scalar($sent) && (string) $range->value === (string) $sent) ?? $ranges[0] ?? null;
    }

    private function timezone(ParsedRules $rules): ?string
    {
        $sent = $this->input('timezone');
        $rules->add('timezone', ['timezone:all']);

        return is_string($sent) && in_array($sent, DateTimeZone::listIdentifiers(), true) ? $sent : null;
    }
}
