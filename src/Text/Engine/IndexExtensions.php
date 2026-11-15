<?php

namespace Lomkit\Rest\Text\Engine;

use Lomkit\Rest\Exceptions\InvalidEngineExtensionException;
use Lomkit\Rest\Extensions\Contexts\EngineProfileContext;
use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Extensions\Kinds\DeclaresIndexAttributes;
use Lomkit\Rest\Extensions\Kinds\ProvidesEngineProfiles;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;
use Lomkit\Rest\Text\Engine\Profiles\MatchesStringLists;

final class IndexExtensions
{
    /** @var array<string, array{class-string, array<mixed>}>|null */
    private ?array $declarations = null;

    /** @param class-string<Resource>|null $resource */
    public function __construct(
        private readonly ?string $resource = null,
        private readonly ?RestContext $context = null,
    ) {}

    public static function of(Resource $resource): self
    {
        return new self($resource::class, $resource->context());
    }

    /** @return array<string, array{class-string, array<mixed>}> */
    public function declarations(EngineProfile $profile): array
    {
        return $this->declarations ??= $this->declare($profile);
    }

    public function profile(object $engine): ?EngineProfile
    {
        if ($this->resource === null || $this->context === null) {
            return null;
        }

        $registry = $this->context->extensions();
        $providers = $registry->applicable($this->resource, ProvidesEngineProfiles::class);

        if ($providers === []) {
            return null;
        }

        $context = new EngineProfileContext($this->resource, $engine);
        $claims = [];

        foreach ($providers as $name => $provider) {
            $profile = $provider->engineProfile($context);

            if ($profile !== null) {
                $claims[$name] = [$registry->classOf($name), $profile];
            }
        }

        return self::single($this->resource, $engine, $claims);
    }

    /** @return array<string, array{class-string, array<mixed>}> */
    private function declare(EngineProfile $profile): array
    {
        if ($this->resource === null || $this->context === null) {
            return [];
        }

        $registry = $this->context->extensions();
        $extensions = $registry->applicable($this->resource, DeclaresIndexAttributes::class);

        if ($extensions === []) {
            return [];
        }

        $context = new IndexAttributesContext($this->resource, $profile instanceof MatchesStringLists);
        $declarations = [];

        foreach ($extensions as $name => $extension) {
            $declarations[$name] = [$registry->classOf($name), $extension->indexAttributes($context)];
        }

        return $declarations;
    }

    /** @param array<string, array{class-string, EngineProfile}> $claims */
    private static function single(string $resource, object $engine, array $claims): ?EngineProfile
    {
        $names = array_keys($claims);

        if (count($names) > 1) {
            [$first, $second] = $names;

            throw InvalidEngineExtensionException::conflictingProfiles($engine::class, $resource, $first, $claims[$first][0], $second, $claims[$second][0]);
        }

        return $names === [] ? null : $claims[$names[0]][1];
    }
}
