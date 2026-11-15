<?php

namespace Lomkit\Rest\Scramble;

use Closure;
use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Discriminator;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Exceptions\InvalidDocumentationException;
use Lomkit\Rest\Scramble\Types\OneOf;

final readonly class ComponentRegistry
{
    public const string OWNER = 'lomkit.owner';

    private const string CONTENT = 'lomkit.content';

    public function __construct(
        private Components $components,
    ) {}

    public function response(string $name, Response $response, string $owner, bool $watched = false): Reference
    {
        $existing = $this->components->responses[$name] ?? null;

        if ($existing === null) {
            $response->setAttribute(self::OWNER, $owner);

            if ($watched) {
                $response->setAttribute(self::CONTENT, self::content($response));
            }

            return $this->components->add(new Reference('responses', $name, $this->components), $response);
        }

        if (! self::same($existing, $response)) {
            $other = $existing instanceof Response ? $existing->getAttribute(self::OWNER, 'the application') : 'the application';

            throw InvalidDocumentationException::componentTaken('response', $name, $owner, is_string($other) ? $other : 'the application');
        }

        return new Reference('responses', $name, $this->components);
    }

    public function ensureResponsesUnchanged(): void
    {
        foreach ($this->components->responses as $name => $response) {
            $registered = $response instanceof Response ? $response->getAttribute(self::CONTENT) : null;

            if (is_string($registered) && $registered !== self::content($response)) {
                $owner = $response->getAttribute(self::OWNER, 'the application');

                throw InvalidDocumentationException::componentChanged('response', $name, is_string($owner) ? $owner : 'the application');
            }
        }
    }

    private static function same(Response|Reference $existing, Response $response): bool
    {
        if ($existing instanceof Response && $existing->code !== $response->code) {
            return false;
        }

        $registered = $existing instanceof Response ? $existing->getAttribute(self::CONTENT) : null;

        return (is_string($registered) ? $registered : self::content($existing)) === self::content($response);
    }

    private static function content(Response|Reference $response): string
    {
        return (string) json_encode($response->serializeAs31());
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

    /** @param list<array{string, string, Closure(): Type}> $branches */
    public function discriminated(string $property, string $owner, array $branches): OneOf
    {
        $discriminator = new Discriminator($property);
        $items = [];

        foreach ($branches as [$value, $name, $build]) {
            $reference = $this->schema($name, $owner, $build);
            $discriminator->addMapping($value, $reference->getUri());
            $items[] = $reference;
        }

        return new OneOf($items, $discriminator);
    }
}
