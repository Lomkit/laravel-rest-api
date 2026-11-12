<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use LogicException;
use Lomkit\Rest\Exceptions\PivotPersisterException;
use Lomkit\Rest\Fields\MutationOperation;

final readonly class PivotWrite
{
    /**
     * @param  list<Model>  $related
     * @param  array<string, mixed>  $pivot
     */
    public function __construct(
        public Model $parent,
        public string $relation,
        public array $related,
        public array $pivot,
        public MutationNode $entry,
        private ?Authenticatable $user = null,
    ) {
        if ($related === []) {
            throw PivotPersisterException::noRelatedRows($relation);
        }
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    /** @return BelongsToMany<Model, Model> */
    public function pivots(): BelongsToMany
    {
        $pivots = $this->parent->{$this->relation}();

        if (! $pivots instanceof BelongsToMany) {
            throw PivotPersisterException::notManyToMany($this->parent::class, $this->relation);
        }

        return $pivots;
    }

    /** @return list<int|string> */
    public function keys(): array
    {
        return array_map(self::key(...), $this->related);
    }

    public function keyPath(): string
    {
        return $this->entry->operation === MutationOperation::Create ? $this->entry->path : $this->entry->path.'.key';
    }

    private static function key(Model $row): int|string
    {
        $key = $row->getKey();

        return is_int($key) || is_string($key) ? $key : throw new LogicException($row::class.' has a key that cannot name a pivot row.');
    }
}
