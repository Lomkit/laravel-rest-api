<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

/** @internal */
interface FiltersStringLists extends MatchesStringLists
{
    /**
     * @param  array<string, list<int|float|string|bool>>  $memberships
     * @param  array<string, list<int|float|string|bool>>  $exclusions
     * @return array<string, mixed>
     */
    public function stringListOptions(array $memberships, array $exclusions): array;
}
