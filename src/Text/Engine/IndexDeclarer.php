<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;

final readonly class IndexDeclarer
{
    /** @param class-string<Resource> $resource */
    private function __construct(
        private string $resource,
        private ?string $extension = null,
        private string $class = '',
    ) {}

    /** @param class-string<Resource> $resource */
    public static function resource(string $resource): self
    {
        return new self($resource);
    }

    /** @param class-string<Resource> $resource */
    public static function extension(string $resource, string $name, string $class): self
    {
        return new self($resource, $name, $class);
    }

    public function invalidName(string $attribute): InvalidTextSearchException|InvalidEngineExtensionException
    {
        return $this->extension === null
            ? InvalidTextSearchException::invalidAttributeName($this->resource, $attribute)
            : InvalidEngineExtensionException::invalidIndexAttributeName($this->extension, $this->class, $this->resource, $attribute);
    }

    public function unsupportedType(string $attribute, string $type): InvalidTextSearchException|InvalidEngineExtensionException
    {
        return $this->extension === null
            ? InvalidTextSearchException::unsupportedAttributeType($this->resource, $attribute, $type)
            : InvalidEngineExtensionException::unsupportedIndexAttributeType($this->extension, $this->class, $this->resource, $attribute, $type);
    }

    public function callback(string $attribute): InvalidTextSearchException|InvalidEngineExtensionException
    {
        return $this->extension === null
            ? InvalidTextSearchException::callbackAttribute($this->resource, $attribute)
            : InvalidEngineExtensionException::callbackIndexAttribute($this->extension, $this->class, $this->resource, $attribute);
    }

    public function unfilterable(string $attribute): InvalidTextSearchException|InvalidEngineExtensionException
    {
        return $this->extension === null
            ? InvalidTextSearchException::unfilterableAttribute($this->resource, $attribute)
            : InvalidEngineExtensionException::unfilterableIndexAttribute($this->extension, $this->class, $this->resource, $attribute);
    }
}
