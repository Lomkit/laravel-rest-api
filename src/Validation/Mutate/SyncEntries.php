<?php

namespace Lomkit\Rest\Validation\Mutate;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\Violation;

final readonly class SyncEntries
{
    public function __construct(private ParsedRules $rules) {}

    /** @param list<MutationNode> $nodes */
    public function check(Relation $relation, array $nodes): void
    {
        if (! $relation->syncsChildren()) {
            return;
        }

        $sync = null;

        foreach ($nodes as $node) {
            if ($node->operation !== MutationOperation::Sync) {
                continue;
            }

            if ($sync !== null) {
                $this->rules->reject(Violation::repeatedSync($node->path.'.operation'));

                continue;
            }

            $sync = $node;
        }

        if ($sync === null) {
            return;
        }

        $listed = self::keys($sync);

        foreach ($nodes as $node) {
            $this->beside($node, $listed);
        }
    }

    /** @param array<string, true> $listed */
    private function beside(MutationNode $node, array $listed): void
    {
        if ($node->operation === MutationOperation::Create || $node->operation === MutationOperation::Sync) {
            return;
        }

        $keys = self::keys($node);
        $removes = $node->operation === MutationOperation::Delete || $node->operation === MutationOperation::Detach;

        if (! $removes && $node->operation !== MutationOperation::Update) {
            $this->rules->reject(Violation::besideSync($node->path.'.operation'));

            return;
        }

        if ($removes && array_intersect_key($keys, $listed) !== []) {
            $this->rules->reject(Violation::keptBySync($node->path.'.key'));
        }

        if (! $removes && array_diff_key($keys, $listed) !== []) {
            $this->rules->reject(Violation::unlistedBySync($node->path.'.key'));
        }
    }

    /** @return array<string, true> */
    private static function keys(MutationNode $node): array
    {
        $keys = [];

        foreach (is_array($node->key) ? $node->key : [$node->key] as $key) {
            if ($key !== null) {
                $keys[(string) $key] = true;
            }
        }

        return $keys;
    }
}
