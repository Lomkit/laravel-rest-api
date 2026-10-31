<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Text\ResolvedText;

class Search extends RestRule
{
    private bool $nested = false;

    public function nested(): static
    {
        $this->nested = true;

        return $this;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function buildValidationRules(string $attribute, mixed $value): array
    {
        $request = $this->resource->context()->request;
        $text = $this->nested ? null : $this->resource->context()->text($this->resource);
        $scout = $text?->usesScout() ?? false;

        return [
            $attribute.'.limit' => ['sometimes', 'integer', Rule::in($this->resource->getLimits($request))],
            $attribute.'.page' => ['sometimes', 'integer', 'min:1'],
            $attribute.'.filters' => ['sometimes', 'array'],
            $attribute.'.gates' => ['sometimes', 'array', Rule::in(['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'])],
            $attribute.'.filters.*' => $scout ? (new ScoutFilter)->setResource($this->resource) : (new SearchFilter)->setResource($this->resource),
            $attribute.'.scopes' => ['sometimes', 'array', Rule::prohibitedIf($scout)],
            $attribute.'.scopes.*' => $scout ? [] : (new SearchScope)->setResource($this->resource),
            $attribute.'.sorts' => ['sometimes', 'array'],
            $attribute.'.sorts.*' => (new SearchSort)->setResource($this->resource)->among($this->sortNames($text)),
            $attribute.'.selects' => ['sometimes', 'array'],
            $attribute.'.selects.*' => (new SearchSelect)->setResource($this->resource),
            $attribute.'.aggregates' => ['sometimes', 'array'],
            $attribute.'.aggregates.*' => (new SearchAggregate)->setResource($this->resource),
            $attribute.'.includes' => ['sometimes', 'array'],
            $attribute.'.includes.*' => (new SearchInclude)->setResource($this->resource),
            $attribute.'.instructions' => ['sometimes', 'array'],
            $attribute.'.instructions.*' => (new SearchInstruction)
                ->setResource($this->resource)
                ->among($scout ? $this->resource->getScoutInstructions($request) : $this->resource->getInstructions($request)),
            ...($text === null ? [] : $this->rootRules($attribute)),
        ];
    }

    /** @return array<string, list<mixed>> */
    private function rootRules(string $attribute): array
    {
        return [
            $attribute.'.text' => ['sometimes', 'array', (new SearchText)->setResource($this->resource)],
            $attribute.'.trashed' => Trashed::supportedBy($this->resource::modelClass()) ? ['sometimes', Rule::enum(Trashed::class)] : ['prohibited'],
        ];
    }

    /** @return list<string> */
    private function sortNames(?ResolvedText $text): array
    {
        $request = $this->resource->context()->request;

        if ($text?->usesScout() === true) {
            return $this->resource->getScoutFields($request);
        }

        $names = $this->resource
            ->getFields($request)
            ->sortable()
            ->names();

        return $text?->ranks() === true ? [...$names, RelevanceClause::FIELD] : $names;
    }
}
