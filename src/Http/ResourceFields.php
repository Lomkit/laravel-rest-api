<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Mutations\RowVersion;
use ReflectionClass;

/** @internal */
final class ResourceFields
{
    private ?FieldSet $fields = null;

    private ?RowVersion $version = null;

    public function __construct(private readonly Resource $resource) {}

    public function fields(): FieldSet
    {
        return $this->fields ??= $this->declared();
    }

    public function version(): ?RowVersion
    {
        $this->fields();

        return $this->version;
    }

    private function declared(): FieldSet
    {
        $fields = FieldSet::of($this->resource->fields(), $this->resource::class.'::fields()');
        $config = $this->resource->context()->config();

        if ($fields->has($config->gatesKey)) {
            throw InvalidResourceException::gatesKeyField($this->resource::class, $config->gatesKey);
        }

        if ($fields->has($config->moreKey)) {
            throw InvalidResourceException::moreKeyField($this->resource::class, $config->moreKey);
        }

        if (! (new ReflectionClass($this->resource::modelClass()))->isInstantiable()) {
            return $fields;
        }

        $model = $this->resource::newModel();
        $bound = $fields->bindTo($model);

        if ($this->resource->versioned()) {
            $this->version = RowVersion::of($this->resource, $model, $bound);
        }

        return $bound;
    }
}
