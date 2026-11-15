<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\ExtensionRegistry;
use Lomkit\Rest\Extensions\Kinds\NarrowsFields;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\NarrowedFields;

/** @internal */
final class FieldNarrowing
{
    /** @var array{string, string} */
    private array $running = ['', ''];

    /** @param array<string, Extension&NarrowsFields> $extensions */
    private function __construct(
        private readonly Resource $resource,
        private readonly ExtensionRegistry $registry,
        private readonly array $extensions,
    ) {}

    public static function of(Resource $resource): ?self
    {
        $context = $resource->context();

        if ($context->isDocumentation()) {
            return null;
        }

        $registry = $context->extensions();
        $extensions = $registry->applicable($resource::class, NarrowsFields::class);

        if ($extensions === []) {
            return null;
        }

        return new self($resource, $registry, $extensions);
    }

    /** @return array{string, string} */
    public function running(): array
    {
        return $this->running;
    }

    public function narrowed(FieldSet $declared, ?string $version, ?string $key): NarrowedFields
    {
        $requests = new FieldRequests($this->resource, $declared, $version, $key);
        $user = $this->resource->context()->actingUser()->user;

        foreach ($this->extensions as $name => $extension) {
            $class = $this->registry->classOf($name);
            $this->running = [$name, $class];
            $extension->narrowFields(new FieldsContext($name, $class, $this->resource, $user, $requests));
        }

        return $requests->narrowed();
    }
}
