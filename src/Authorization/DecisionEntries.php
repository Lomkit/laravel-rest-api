<?php

namespace Lomkit\Rest\Authorization;

/** @internal */
final readonly class DecisionEntries
{
    public function __construct(
        private int $since,
        private ?DecisionVersions $versions,
    ) {}

    /**
     * @param  array<string, Check>  $checks
     * @return array<string, array{DecisionKey, int}>
     */
    public function of(array $checks): array
    {
        $abilities = [];
        $entries = [];
        $contexts = [];

        foreach ($checks as $key => $check) {
            if ($check->isKeyedByInstance()) {
                continue;
            }

            $cached = $abilities[spl_object_id($check->resource)] ??= $check->resource->definition()->cachedAbilities();
            $seconds = $cached->seconds($check->ability);
            $decisionKey = $seconds === null ? null : DecisionKey::of($check, $cached->context, $this->since);

            if ($seconds !== null && $decisionKey !== null) {
                $entries[$key] = [$decisionKey, $seconds];
                $contexts[$key] = $cached->context;
            }
        }

        return $this->versions === null ? $entries : $this->versioned($this->versions, $checks, $entries, $contexts);
    }

    /**
     * @param  array<string, Check>  $checks
     * @param  array<string, array{DecisionKey, int}>  $entries
     * @param  array<string, string>  $contexts
     * @return array<string, array{DecisionKey, int}>
     */
    private function versioned(DecisionVersions $versions, array $checks, array $entries, array $contexts): array
    {
        $groups = [];
        $byModel = [];

        foreach (array_keys($entries) as $key) {
            foreach ($checks[$key]->models() as $index => $model) {
                $resource = $checks[$key]->resourceOf($model);

                if ($versions->appliesTo($resource)) {
                    $group = $resource::class.'|'.$checks[$key]->ability;
                    $groups[$group] ??= [$resource, $checks[$key]->ability, []];
                    $groups[$group][2][spl_object_id($model)] = $model;
                    $byModel[$key][$index] = $group;
                }
            }
        }

        $collected = array_map(
            fn (array $group): DependentVersions => $versions->collect($group[0], $group[1], array_values($group[2]), $this->since),
            $groups,
        );

        foreach ($byModel as $key => $models) {
            $decisionKey = DecisionKey::of($checks[$key], $contexts[$key], $this->since, array_map(fn (string $group): DependentVersions => $collected[$group], $models));

            if ($decisionKey === null) {
                unset($entries[$key]);
            } else {
                $entries[$key] = [$decisionKey, $entries[$key][1]];
            }
        }

        return $entries;
    }
}
