<?php

namespace Lomkit\Rest\Authorization;

/** @internal */
final readonly class DecisionEntries
{
    public function __construct(
        private int $since,
        private DecisionVersions $versions = new DecisionVersions,
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
        $versioned = [];

        foreach ($checks as $key => $check) {
            if ($check->isKeyedByInstance()) {
                continue;
            }

            $cached = $abilities[spl_object_id($check->resource)] ??= $check->resource->definition()->cachedAbilities();
            $seconds = $cached->seconds($check->ability);
            $decisionKey = $seconds === null ? null : DecisionKey::of($check, $cached->context, $this->since);

            if ($seconds === null || $decisionKey === null) {
                continue;
            }

            $entries[$key] = [$decisionKey, $seconds];
            $contexts[$key] = $cached->context;

            if ($check->models() !== [] && $this->versions->appliesTo($check->resource)) {
                $versioned[$check->resource::class.'|'.$check->ability][$key] = $check;
            }
        }

        foreach ($versioned as $group) {
            $versions = $this->versions->collect(array_values($group), $this->since);

            foreach ($group as $key => $check) {
                $decisionKey = DecisionKey::of($check, $contexts[$key], $this->since, $versions);

                if ($decisionKey === null) {
                    unset($entries[$key]);
                } else {
                    $entries[$key] = [$decisionKey, $entries[$key][1]];
                }
            }
        }

        return $entries;
    }
}
