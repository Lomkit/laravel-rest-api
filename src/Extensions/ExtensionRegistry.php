<?php

namespace Lomkit\Rest\Extensions;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Extensions\Kinds\ExtensionKind;
use Lomkit\Rest\Http\Resource;

final class ExtensionRegistry
{
    /** @var array<string, Extension>|null */
    private ?array $built = null;

    /** @var array<string, array<class-string<Resource>, bool>> */
    private array $answers = [];

    /** @param array<string, string> $classes */
    private function __construct(
        private readonly array $classes,
        private readonly Container $container,
    ) {}

    /**
     * @param  array<array-key, mixed>  $configured
     * @param  array<string, string>  $registered
     */
    public static function of(array $configured, array $registered, Container $container): self
    {
        if ($configured === [] && $registered === []) {
            return new self([], $container);
        }

        return new self(ExtensionList::merge($configured, $registered)->enabled(), $container);
    }

    public function isEmpty(): bool
    {
        return $this->classes === [];
    }

    /** @return array<string, Extension> */
    public function all(): array
    {
        if ($this->classes === []) {
            return [];
        }

        return $this->built ??= $this->build();
    }

    /**
     * @template TKind of ExtensionKind
     *
     * @param  class-string<Resource>  $resource
     * @param  class-string<TKind>  $kind
     * @return array<string, Extension&TKind>
     */
    public function applicable(string $resource, string $kind): array
    {
        $applicable = [];

        foreach ($this->all() as $name => $extension) {
            if ($extension instanceof $kind && $this->applies($name, $extension, $resource)) {
                $applicable[$name] = $extension;
            }
        }

        return $applicable;
    }

    /** @param class-string<Resource> $resource */
    private function applies(string $name, Extension $extension, string $resource): bool
    {
        return $this->answers[$name][$resource] ??= $extension->appliesTo($resource);
    }

    /** @return array<string, Extension> */
    private function build(): array
    {
        $factory = new ExtensionFactory($this->container);
        $built = [];

        foreach ($this->classes as $name => $class) {
            $built[$name] = $factory->make($name, $class);
        }

        return $built;
    }
}
