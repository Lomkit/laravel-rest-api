<?php

namespace Lomkit\Rest\Scramble;

use Closure;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Exceptions\InvalidDocumentationException;

final readonly class ComponentRegistry
{
    public const string OWNER = 'lomkit.owner';

    public function __construct(
        private Components $components,
    ) {}

    public function response(string $name, Response $response, string $owner): Reference
    {
        $existing = $this->components->responses[$name] ?? null;

        if ($existing === null) {
            $response->setAttribute(self::OWNER, $owner);

            return $this->components->add(new Reference('responses', $name, $this->components), $response);
        }

        if (json_encode($existing->serializeAs31()) !== json_encode($response->serializeAs31())) {
            $other = $existing instanceof Response ? $existing->getAttribute(self::OWNER, 'the application') : 'the application';

            throw InvalidDocumentationException::componentTaken('response', $name, $owner, is_string($other) ? $other : 'the application');
        }

        return new Reference('responses', $name, $this->components);
    }

    /** @param Closure(): Type $build */
    public function schema(string $name, string $owner, Closure $build, ?string $fallback = null): Reference
    {
        $existing = $this->components->schemas[$name] ?? null;

        if ($existing !== null) {
            $other = $existing instanceof Schema ? $existing->type->getAttribute(self::OWNER, 'the application') : 'the application';

            if ($other !== $owner && $fallback !== null) {
                return $this->schema($fallback, $owner, $build);
            }

            if ($other !== $owner) {
                throw InvalidDocumentationException::componentTaken('schema', $name, $owner, is_string($other) ? $other : 'the application');
            }

            return new Reference('schemas', $name, $this->components);
        }

        $placeholder = new ObjectType;
        $placeholder->setAttribute(self::OWNER, $owner);
        $schema = new Schema;
        $schema->type = $placeholder;
        $reference = $this->components->addSchema($name, $schema);

        $schema->type = $build();
        $schema->type->setAttribute(self::OWNER, $owner);

        return $reference;
    }
}
