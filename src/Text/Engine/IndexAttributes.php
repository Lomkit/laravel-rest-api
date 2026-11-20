<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\FiltersWithGroups;
use Lomkit\Rest\Text\Engine\Totals\TotalsAttributes;
use Lomkit\Rest\Text\Engine\Totals\TotalsGrammar;
use Lomkit\Rest\Text\TextTarget;

final readonly class IndexAttributes
{
    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<string, IndexAttribute>  $server
     * @param  list<string>  $sortable
     * @param  array<string, IndexAttribute>  $owned
     */
    private function __construct(
        private array $filterable,
        private array $server,
        private array $sortable,
        private array $owned,
        private ?TotalsGrammar $totals = null,
    ) {}

    /**
     * @param  array<mixed>  $filterable
     * @param  array<mixed>  $sortable
     * @param  array<mixed>  $perimeter
     * @param  array<mixed>  $indexAttributes
     * @param  array{array<mixed>, array<mixed>}  $totals
     */
    public static function of(TextTarget $target, EngineProfile $profile, array $filterable, array $sortable, array $perimeter, bool $lists = false, array $indexAttributes = [], array $totals = [[], []]): self
    {
        $client = DeclaredAttributes::of($target, $profile, $filterable, 'filterable()');
        $bounding = DeclaredAttributes::of($target, $profile, $perimeter, 'perimeter()', true);
        $indexed = DeclaredAttributes::of($target, $profile, $indexAttributes, 'indexAttributes()', true, true);

        foreach ([...array_keys($bounding), ...array_keys($indexed)] as $name) {
            if (isset($client[$name]) || (isset($bounding[$name]) && isset($indexed[$name]))) {
                throw InvalidTextSearchException::duplicateAttribute($target->resource, $name);
            }
        }

        $sorts = DeclaredAttributes::sorts($target, $profile, $client, $sortable, $lists);

        $owned = ExtensionAttributes::of($target, $profile, $client, $bounding, $indexed);

        return new self($client, $bounding + $indexed, $sorts, $owned, TotalsAttributes::of($target, $profile, $client, $totals[0], $totals[1]));
    }

    public function find(string $name): ?IndexAttribute
    {
        return $this->filterable[$name] ?? $this->server[$name] ?? $this->owned[$name] ?? null;
    }

    public function declared(string $name): IndexAttribute
    {
        return $this->find($name) ?? throw InvalidEngineQueryException::undeclaredAttribute($name);
    }

    public function declaresTotals(): bool
    {
        return $this->totals?->declares() === true;
    }

    /**
     * @internal
     *
     * @return array<string, int>
     */
    public function totalsScales(): array
    {
        return $this->totals->scales ?? [];
    }

    public function sorts(string $name): bool
    {
        return in_array($name, $this->sortable, true);
    }

    public function grammar(EngineProfile $profile, ?ListOrder $listOrder = null): EngineGrammar
    {
        $grammar = new EngineGrammar($this->filterable, $this->sortable, $profile->sortKeys(), $profile->maxPageSize(), $profile->window(), groups: $profile instanceof FiltersWithGroups, totals: $this->totals);

        return $listOrder === null ? $grammar : $grammar->listing($listOrder->sortKeys(), $listOrder->tieBreaker?->attribute);
    }
}
