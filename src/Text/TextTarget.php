<?php

namespace Lomkit\Rest\Text;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Trashed;

final readonly class TextTarget
{
    /** @param class-string<Resource> $resource */
    public function __construct(
        public string $resource,
        public FieldSet $fields,
        public Model $model,
        public FieldSet $declared,
    ) {}

    public static function of(Resource $resource, RestRequest $request): self
    {
        $declared = $resource->getFields($request);

        return new self($resource::class, $declared->searchable(), $resource::newModel(), $declared);
    }

    /** @return non-empty-list<string> */
    public function names(): array
    {
        $names = $this->fields->names();

        if ($names === []) {
            throw InvalidTextSearchException::noSearchableField($this->resource);
        }

        return $names;
    }

    public function driver(): string
    {
        return $this->model->getConnection()->getDriverName();
    }

    public function table(): string
    {
        return $this->model->getTable();
    }

    public function softDeletes(): bool
    {
        return Trashed::supportedBy($this->model);
    }

    /** @return list<string> */
    public function columns(): array
    {
        return array_map(fn (string $name): string => $this->model->qualifyColumn($name), $this->names());
    }
}
