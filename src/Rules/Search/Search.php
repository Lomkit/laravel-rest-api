<?php

namespace Lomkit\Rest\Rules\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Instructions\InstructionSupport;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Rules\RestRule;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\ResolvedText;
use Lomkit\Rest\Text\TextBackend;

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
        $engine = $text?->engineGrammar();
        $search = is_array($value) ? $value : [];

        return [
            $attribute.'.limit' => ['sometimes', 'integer', Rule::in($this->resource->getLimits($request)), ...self::limitRules($engine)],
            $attribute.'.page' => ['sometimes', 'integer', 'min:1', ...$this->pageRules($engine, $search['limit'] ?? null)],
            $attribute.'.filters' => ['sometimes', 'array'],
            $attribute.'.gates' => ['sometimes', 'array', Rule::in(['viewAny', 'view', 'create', 'update', 'delete', 'restore', 'forceDelete'])],
            $attribute.'.filters.*' => ($engine === null ? new SearchFilter : new EngineFilter($engine))->setResource($this->resource),
            $attribute.'.scopes' => ['sometimes', 'array', Rule::prohibitedIf($engine !== null)],
            $attribute.'.scopes.*' => $engine === null ? (new SearchScope)->setResource($this->resource) : [],
            $attribute.'.sorts' => ['sometimes', 'array', ...self::sortCountRules($engine)],
            $attribute.'.sorts.*' => $this->sortRule($text, $engine, $search['sorts'] ?? null),
            $attribute.'.selects' => ['sometimes', 'array'],
            $attribute.'.selects.*' => (new SearchSelect)->setResource($this->resource),
            $attribute.'.aggregates' => ['sometimes', 'array'],
            $attribute.'.aggregates.*' => (new SearchAggregate)->setResource($this->resource),
            $attribute.'.includes' => ['sometimes', 'array'],
            $attribute.'.includes.*' => (new SearchInclude)->setResource($this->resource),
            $attribute.'.instructions' => ['sometimes', 'array'],
            $attribute.'.instructions.*' => (new SearchInstruction)
                ->setResource($this->resource)
                ->among(InstructionSupport::among($this->resource->getInstructions($request), $engine === null ? TextBackend::Query : TextBackend::Engine)),
            ...($text === null ? [] : $this->rootRules($attribute, $text)),
        ];
    }

    /** @return array<string, list<mixed>> */
    private function rootRules(string $attribute, ResolvedText $text): array
    {
        $trashed = $text->usesEngine()
            ? $text->capabilities?->trashed === true
            : Trashed::supportedBy($this->resource::modelClass());

        return [
            $attribute.'.text' => ['sometimes', 'array', (new SearchText)->setResource($this->resource)],
            $attribute.'.trashed' => $trashed ? ['sometimes', Rule::enum(Trashed::class)] : ['prohibited'],
        ];
    }

    private function sortRule(?ResolvedText $text, ?EngineGrammar $engine, mixed $sorts): RestRule
    {
        if ($engine !== null) {
            return (new EngineSort($engine, is_array($sorts) && count($sorts) === 1))->setResource($this->resource);
        }

        return (new SearchSort)
            ->setResource($this->resource)
            ->among($this->sortNames($text));
    }

    /** @return list<string> */
    private function sortNames(?ResolvedText $text): array
    {
        $names = $this->resource
            ->getFields($this->resource->context()->request)
            ->sortable()
            ->names();

        return $text?->ranks() === true ? [...$names, RelevanceClause::FIELD] : $names;
    }

    /** @return list<string> */
    private function pageRules(?EngineGrammar $engine, mixed $limit): array
    {
        $window = $engine?->window;

        if ($window === null) {
            return [];
        }

        $perPage = filter_var($limit, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return ['max:'.intdiv($window, $perPage === false ? $this->resource->defaultLimit : $perPage)];
    }

    /** @return list<string> */
    private static function limitRules(?EngineGrammar $engine): array
    {
        $maximum = $engine?->maxPageSize;

        return $maximum === null ? [] : ['max:'.$maximum];
    }

    /** @return list<string> */
    private static function sortCountRules(?EngineGrammar $engine): array
    {
        if ($engine === null || $engine->sortKeys === PHP_INT_MAX) {
            return [];
        }

        return ['max:'.max(1, $engine->sortKeys)];
    }
}
