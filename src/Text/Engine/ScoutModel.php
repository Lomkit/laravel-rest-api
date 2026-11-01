<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Query\Trashed;

final readonly class ScoutModel
{
    private function __construct(private Model $model) {}

    public static function tryFrom(Model $model): ?self
    {
        return in_array(Searchable::class, class_uses_recursive($model), true) ? new self($model) : null;
    }

    public function model(): Model
    {
        return $this->model;
    }

    public function engine(): mixed
    {
        return method_exists($this->model, 'searchableUsing') ? $this->model->searchableUsing() : null;
    }

    public function indexName(): string
    {
        $name = method_exists($this->model, 'searchableAs') ? $this->model->searchableAs() : null;

        return is_string($name) ? $name : throw InvalidResourceException::invalidScoutAttribute($this->model::class, 'searchableAs');
    }

    public function keyName(): string
    {
        $name = method_exists($this->model, 'getScoutKeyName') ? $this->model->getScoutKeyName() : null;

        return is_string($name) && $name !== '' ? $name : throw InvalidResourceException::invalidScoutAttribute($this->model::class, 'getScoutKeyName');
    }

    public function keyColumn(): string
    {
        return $this->model->qualifyColumn($this->keyName());
    }

    public function integerKeys(): bool
    {
        $type = method_exists($this->model, 'getScoutKeyType') ? $this->model->getScoutKeyType() : null;

        return in_array($type, ['int', 'integer'], true);
    }

    public function key(mixed $key): int|string
    {
        if ($this->integerKeys()) {
            $integer = is_int($key) || is_string($key) ? filter_var($key, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE) : null;

            return $integer ?? throw InvalidResourceException::integerScoutKey($this->model::class, $key);
        }

        return is_string($key) || is_int($key) ? (string) $key : throw InvalidResourceException::invalidScoutKey($this->model::class, $key);
    }

    /**
     * @param  iterable<mixed>  $keys
     * @return list<int|string>
     */
    public function keys(iterable $keys): array
    {
        $normalized = [];

        foreach ($keys as $key) {
            $normalized[] = $this->key($key);
        }

        return $normalized;
    }

    public function keyOf(Model $row): int|string
    {
        return $this->key(method_exists($row, 'getScoutKey') ? $row->getScoutKey() : null);
    }

    public function softDeletes(): bool
    {
        return Trashed::supportedBy($this->model) && (bool) config('scout.soft_delete', false);
    }
}
