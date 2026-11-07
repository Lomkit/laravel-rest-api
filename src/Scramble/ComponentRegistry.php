<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Components;
use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Response;
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
}
