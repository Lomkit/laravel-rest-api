<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Validation\Violation;

final class PivotLedger
{
    private const int CHUNK = 1000;

    /** @var list<array{string, MutationNode, list<int|string>}> */
    private array $moves = [];

    /** @var array<string, BelongsToMany<Model, Model>> */
    private array $relations = [];

    /** @var array<string, array<array-key, int|string>> */
    private array $seeds = [];

    /** @var array<string, array<array-key, bool>> */
    private array $links = [];

    /**
     * @param  BelongsToMany<Model, Model>  $pivots
     * @param  list<LinkedRow>  $rows
     */
    public function add(LinkedRow $parent, BelongsToMany $pivots, MutationNode $node, array $rows): void
    {
        if ($node->operation === MutationOperation::Create) {
            return;
        }

        $group = $parent->identity."\n".$pivots->getRelationName();
        $children = array_map(fn (LinkedRow $row): int|string => self::key($row->model->getKey()), $rows);

        if ($parent->model->exists && $node->operation === MutationOperation::Attach && $children !== []) {
            $this->relations[$group] = $pivots;

            foreach ($children as $child) {
                $this->seeds[$group][$child] = $child;
            }
        }

        $this->moves[] = [$group, $node, $children];
    }

    /** @return array<string, Violation> */
    public function violations(): array
    {
        $this->seed();
        $violations = [];

        foreach ($this->moves as [$group, $node, $children]) {
            if ($node->operation === MutationOperation::Sync && $node->withoutDetaching !== true) {
                $this->links[$group] = array_fill_keys(array_keys($this->links[$group] ?? []), false);
            }

            foreach ($children as $child) {
                $linked = $this->links[$group][$child] ?? false;

                if ($linked && $node->operation === MutationOperation::Attach) {
                    $violations[$node->path.'.key'] ??= Violation::attachedRecord($node->path.'.key');
                }

                $this->links[$group][$child] = match ($node->operation) {
                    MutationOperation::Detach => false,
                    MutationOperation::Toggle => ! $linked,
                    default => true,
                };
            }
        }

        return $violations;
    }

    private function seed(): void
    {
        foreach ($this->relations as $group => $pivots) {
            foreach (array_chunk(array_values($this->seeds[$group]), self::CHUNK) as $keys) {
                $linked = $pivots
                    ->newPivotQuery()
                    ->whereIn($pivots->getQualifiedRelatedPivotKeyName(), $keys)
                    ->pluck($pivots->getRelatedPivotKeyName());

                foreach ($linked as $key) {
                    $this->links[$group][self::key($key)] = true;
                }
            }
        }
    }

    private static function key(mixed $key): int|string
    {
        return is_int($key) || is_string($key) ? $key : throw new LogicException('A pivot row has a key that cannot name it.');
    }
}
