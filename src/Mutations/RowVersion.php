<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Validation\KeysGrammar;

final readonly class RowVersion
{
    private const int CHUNK = 1000;

    private function __construct(
        public string $column,
        public Field $field,
    ) {}

    public static function of(Resource $resource, Model $model, FieldSet $fields): self
    {
        $column = $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null;

        if ($column === null) {
            throw InvalidResourceException::versionWithoutTimestamps($resource::class);
        }

        $field = $fields->find($column);

        if ($field === null) {
            throw InvalidResourceException::versionNotAField($resource::class, $column);
        }

        return new self($column, $field);
    }

    /**
     * @param  list<int|string>  $keys
     * @return array<array-key, mixed>
     */
    public function lock(Resource $resource, array $keys): array
    {
        $model = $resource::newModel();
        $id = KeysGrammar::idOf($resource);
        $versions = [];

        foreach (array_chunk($keys, self::CHUNK) as $chunk) {
            $query = $model
                ->newQueryWithoutScopes()
                ->select([$model->getKeyName(), $this->column])
                ->lockForUpdate();

            foreach ($id->whereKeys($query, $chunk)->get() as $row) {
                $key = $id->parseKey($row->getRawOriginal($model->getKeyName()));

                if ($key !== null) {
                    $versions[$id->normalizeKey($key)] = $this->read($row);
                }
            }
        }

        return $versions;
    }

    public function read(Model $row): mixed
    {
        $values = $row
            ->setVisible([$this->column])
            ->withoutAppends()
            ->attributesToArray();

        return $this->field->fromStorage($values[$this->column] ?? null);
    }
}
