<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Extensions\Kinds\NarrowsFields;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\NarrowedFields;

/** @internal */
final class FieldNarrowing
{
    private string $running = '';

    public function __construct(
        private readonly Resource $resource,
        private readonly FieldSet $declared,
        private readonly ?string $version,
        private readonly ?string $key,
    ) {}

    public function running(): string
    {
        return $this->running;
    }

    public function narrowed(): NarrowedFields
    {
        $context = $this->resource->context();

        if ($context->isDocumentation()) {
            return NarrowedFields::none();
        }

        $extensions = $context->extensions()->applicable($this->resource::class, NarrowsFields::class);

        if ($extensions === []) {
            return NarrowedFields::none();
        }

        ksort($extensions);
        $requests = new FieldRequests($this->resource, $this->declared, $this->version, $this->key);
        $user = $context->actingUser()->user;

        foreach ($extensions as $name => $extension) {
            $this->running = $name;
            $extension->narrowFields(new FieldsContext($name, $this->resource, $user, $requests));
        }

        return $requests->narrowed();
    }
}
