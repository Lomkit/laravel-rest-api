<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineQueryException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\FiltersWithGroups;
use Lomkit\Rest\Text\TextTarget;

final readonly class IndexAttributes
{
    /**
     * @param  array<string, IndexAttribute>  $filterable
     * @param  array<string, IndexAttribute>  $perimeter
     * @param  list<string>  $sortable
     * @param  array<string, IndexAttribute>  $owned
     */
    private function __construct(
        private array $filterable,
        private array $perimeter,
        private array $sortable,
        private array $owned,
    ) {}

    /**
     * @param  array<mixed>  $filterable
     * @param  array<mixed>  $sortable
     * @param  array<mixed>  $perimeter
     */
    public static function of(TextTarget $target, EngineProfile $profile, array $filterable, array $sortable, array $perimeter, bool $lists = false): self
    {
        $client = DeclaredAttributes::of($target, $profile, $filterable, 'filterable()');
        $restricted = DeclaredAttributes::of($target, $profile, $perimeter, 'perimeter()', true);

        foreach (array_keys($restricted) as $name) {
            if (isset($client[$name])) {
                throw InvalidTextSearchException::duplicateAttribute($target->resource, $name);
            }
        }

        $sorts = DeclaredAttributes::sorts($target, $profile, $client, $sortable, $lists);

        return new self($client, $restricted, $sorts, ExtensionAttributes::of($target, $profile, $client, $restricted));
    }

    public function find(string $name): ?IndexAttribute
    {
        return $this->filterable[$name] ?? $this->perimeter[$name] ?? $this->owned[$name] ?? null;
    }

    public function declared(string $name): IndexAttribute
    {
        return $this->find($name) ?? throw InvalidEngineQueryException::undeclaredAttribute($name);
    }

    public function sorts(string $name): bool
    {
        return in_array($name, $this->sortable, true);
    }

    public function grammar(EngineProfile $profile, ?ListOrder $lists = null): EngineGrammar
    {
        $grammar = new EngineGrammar($this->filterable, $this->sortable, $profile->sortKeys(), $profile->maxPageSize(), $profile->window(), groups: $profile instanceof FiltersWithGroups);

        return $lists === null ? $grammar : $grammar->listing($lists->sortKeys(), $lists->tieBreaker?->attribute);
    }
}
