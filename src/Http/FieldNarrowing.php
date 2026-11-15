<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Extensions\Contexts\FieldsContext;
use Lomkit\Rest\Extensions\Kinds\NarrowsFields;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\NarrowedFields;

/** @internal */
final readonly class FieldNarrowing
{
    public static function of(Resource $resource, FieldSet $declared, ?string $version, ?string $key): NarrowedFields
    {
        $context = $resource->context();

        if ($context->isDocumentation()) {
            return NarrowedFields::none();
        }

        $extensions = $context->extensions()->applicable($resource::class, NarrowsFields::class);

        if ($extensions === []) {
            return NarrowedFields::none();
        }

        ksort($extensions);
        $requests = new FieldRequests($resource, $declared, $version, $key);
        $user = $context->actingUser()->user;

        foreach ($extensions as $name => $extension) {
            $extension->narrowFields(new FieldsContext($name, $resource, $user, $requests));
        }

        return $requests->narrowed();
    }
}
