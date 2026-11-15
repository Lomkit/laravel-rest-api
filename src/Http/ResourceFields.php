<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidFieldExtensionException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\NarrowedFields;
use Lomkit\Rest\Mutations\RowVersion;
use ReflectionClass;

/** @internal */
final class ResourceFields
{
    private ?FieldSet $declared = null;

    private ?RowVersion $version = null;

    private ?string $key = null;

    private ?NarrowedFields $narrowed = null;

    private ?FieldSet $fields = null;

    private ?FieldNarrowing $narrowing = null;

    public function __construct(private readonly Resource $resource) {}

    public function fields(): FieldSet
    {
        return $this->fields ??= $this->narrowing()->apply($this->declared());
    }

    public function declared(): FieldSet
    {
        return $this->declared ??= $this->declare();
    }

    public function version(): ?RowVersion
    {
        $this->declared();

        return $this->version;
    }

    public function narrowing(): NarrowedFields
    {
        if ($this->narrowed !== null) {
            return $this->narrowed;
        }

        if ($this->narrowing !== null) {
            throw InvalidFieldExtensionException::readsNarrowedFields($this->narrowing->running(), $this->resource::class);
        }

        $declared = $this->declared();
        $this->narrowing = new FieldNarrowing($this->resource, $declared, $this->version?->column, $this->key);

        try {
            return $this->narrowed = $this->narrowing->narrowed();
        } finally {
            $this->narrowing = null;
        }
    }

    private function declare(): FieldSet
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
        $this->key = $model->getKeyName();

        if ($this->resource->versioned()) {
            $this->version = RowVersion::of($this->resource, $model, $bound);
        }

        return $bound;
    }
}
