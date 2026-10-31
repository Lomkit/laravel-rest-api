<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use InvalidArgumentException;
use Lomkit\Rest\Query\Trashed;

final readonly class TrashedClause implements Clause
{
    public function __construct(private Trashed $trashed) {}

    public static function fromPayload(mixed $trashed): ?self
    {
        return is_string($trashed) ? new self(Trashed::from($trashed)) : null;
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        if (! $query instanceof EloquentBuilder || ! method_exists($model = $query->getModel(), 'getQualifiedDeletedAtColumn')) {
            throw new InvalidArgumentException('Trashed models are searched on the Eloquent builder of a model that uses soft deletes.');
        }

        $query->withoutGlobalScope(SoftDeletingScope::class);

        $column = $model->getQualifiedDeletedAtColumn();

        if ($this->trashed === Trashed::With || ! is_string($column)) {
            return;
        }

        $query->whereNotNull($column);
    }
}
