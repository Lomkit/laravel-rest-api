<?php

namespace Lomkit\Rest\Http\Details;

use Closure;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Authorization\ClassAuthorizations;
use Lomkit\Rest\Fields\Details\FieldDetails;
use Lomkit\Rest\Fields\Details\ResourceRuleDetails;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\ResourceDefinition;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scopes\Scope;

final readonly class ResourceDetails
{
    private ResourceDefinition $definition;

    private ClassAuthorizations $authorizations;

    public function __construct(private Resource $resource, private DetailsSelection $selection)
    {
        $this->definition = $resource->definition();
        $this->authorizations = $selection->has('authorizations') ? ClassAuthorizations::of($resource) : ClassAuthorizations::on($resource, $this->entryAbilities());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $sections = array_filter($this->sections(), $this->selection->has(...), ARRAY_FILTER_USE_KEY);

        return array_map(fn (Closure $section): mixed => $section(), $sections);
    }

    /** @return array<string, Closure(): mixed> */
    private function sections(): array
    {
        return [
            'actions' => fn (): array => array_values(array_map($this->action(...), $this->definition->actions())),
            'metrics' => fn (): array => array_values(array_map($this->metric(...), $this->definition->metrics())),
            'instructions' => fn (): array => array_values(array_map(fn (Instruction $instruction): array => $instruction->toDetails($this->selection), $this->definition->instructions())),
            'fields' => fn (): array => FieldDetails::forFields($this->definition->fields(), $this->selection->parts('fields')),
            'limits' => fn (): array => $this->definition->limits()->allowed,
            'scopes' => fn (): array => array_map(fn (Scope $scope): array => $scope->toDetails($this->selection), $this->definition->scopes()),
            'relations' => fn (): array => array_values(array_map(fn (Relation $relation): array => $relation->toDetails($this->selection), $this->definition->relations())),
            'rules' => fn (): array => ResourceRuleDetails::of($this->resource)->jsonSerialize(),
            'text' => fn (): mixed => $this->resource->context()->text($this->resource)->capabilities?->jsonSerialize(),
            'gates' => fn (): array => $this->resource->isGatingEnabled() ? $this->definition->gates()->names() : [],
            'confirmations' => fn (): array => $this->resource->confirmations(),
            'authorizations' => fn (): array => $this->authorizations->jsonSerialize(),
            'meta' => fn (): object => (object) $this->resource->detailsMeta(),
        ];
    }

    /** @return array<string, mixed> */
    private function action(Action $action): array
    {
        $details = $action->toDetails($this->selection);

        if (! $this->selection->has('actions.authorized')) {
            return $details;
        }

        return [...$details, 'authorized' => $this->authorizations->allow($action->entryAbilities())];
    }

    /** @return array<string, mixed> */
    private function metric(Metric $metric): array
    {
        $details = $metric->toDetails($this->selection);

        if (! $this->selection->has('metrics.authorized')) {
            return $details;
        }

        return [...$details, 'authorized' => $this->authorizations->allow($metric->entryAbilities())];
    }

    /** @return list<string> */
    private function entryAbilities(): array
    {
        $actions = $this->selection->has('actions.authorized') ? array_map(fn (Action $action): array => $action->entryAbilities(), array_values($this->definition->actions())) : [];
        $metrics = $this->selection->has('metrics.authorized') ? array_map(fn (Metric $metric): array => $metric->entryAbilities(), array_values($this->definition->metrics())) : [];

        return array_values(array_unique(array_merge([], ...$actions, ...$metrics)));
    }
}
