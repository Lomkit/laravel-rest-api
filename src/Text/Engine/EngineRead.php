<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Exceptions\TotalsComputationException;
use Lomkit\Rest\Query\ExtensionRestrictions;
use Lomkit\Rest\Search\Totals\TotalsNode;
use Lomkit\Rest\Text\Engine\Profiles\ComputesTotals;
use Lomkit\Rest\Text\Engine\Totals\EngineTotals;

/** @internal */
final readonly class EngineRead
{
    public function __construct(
        private EngineIndex $index,
        private ?RestrictedPage $restricted = null,
        private ?EngineTotals $totals = null,
    ) {}

    public function restrictedBy(ExtensionRestrictions $rows): self
    {
        return new self($this->index, RestrictedPage::onto($this->index, $rows, $this->restricted), $this->totals);
    }

    public function restricts(): bool
    {
        return $this->restricted !== null;
    }

    public function totaling(TotalsNode $node): self
    {
        if (! $this->index->attributes->declaresTotals()) {
            throw InvalidTextSearchException::untotalledEngine($this->index->searchedClass(), $this->index->profile->name());
        }

        return new self($this->index, $this->restricted, new EngineTotals($node, $this->index->searchedClass(), $this->index->profile->name(), $this->index->attributes->totalsScales()));
    }

    public function empty(): EnginePage
    {
        return EnginePage::empty()->withTotals($this->totals?->empty());
    }

    /** @param Builder<Model> $builder */
    public function page(Builder $builder, int $perPage, int $page): EnginePage
    {
        $model = $this->index->model;
        $profile = $this->index->profile;

        if ($this->restricted !== null) {
            return $this->restricted->read($builder, $model, $perPage, $page, $this->totals);
        }

        if ($profile instanceof ComputesTotals && $this->index->attributes->declaresTotals()) {
            return $profile->totalsPage($builder, $model, $perPage, $page, $this->totals);
        }

        if ($this->totals !== null) {
            throw TotalsComputationException::untotalledProfile($this->index->searchedClass(), $profile::class);
        }

        return $profile->page($builder, $model, $perPage, $page);
    }
}
