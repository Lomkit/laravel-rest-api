<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Http\Resource;

final readonly class IndexDeclarer
{
    /** @param class-string<Resource> $resource */
    private function __construct(private string $resource) {}

    /** @param class-string<Resource> $resource */
    public static function resource(string $resource): self
    {
        return new self($resource);
    }

    public function invalidName(string $attribute): InvalidTextSearchException
    {
        return InvalidTextSearchException::invalidAttributeName($this->resource, $attribute);
    }

    public function unsupportedType(string $attribute, string $type): InvalidTextSearchException
    {
        return InvalidTextSearchException::unsupportedAttributeType($this->resource, $attribute, $type);
    }

    public function callback(string $attribute): InvalidTextSearchException
    {
        return InvalidTextSearchException::callbackAttribute($this->resource, $attribute);
    }

    public function unfilterable(string $attribute): InvalidTextSearchException
    {
        return InvalidTextSearchException::unfilterableAttribute($this->resource, $attribute);
    }
}
