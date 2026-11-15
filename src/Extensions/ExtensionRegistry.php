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

    /** @param array<string, class-string<Extension>> $classes */
    private function __construct(
        private readonly array $classes,
        private readonly Container $container,
    ) {}

    /**
     * @param  list<mixed>  $configured
     * @param  list<mixed>  $disabled
     * @param  list<string>  $registered
     */
    public static function of(array $configured, array $disabled, array $registered, Container $container): self
    {
        if ($configured === [] && $disabled === [] && $registered === []) {
            return new self([], $container);
        }

        return new self(ExtensionList::of($configured, $disabled, $registered)->named()->enabled(), $container);
    }

    /** @return array<string, Extension> */
    public function all(): array
    {
        if ($this->classes === []) {
            return [];
        }

        return $this->built ??= $this->build();
    }

    /** @return class-string<Extension> */
    public function classOf(string $name): string
    {
        return $this->classes[$name];
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
        return array_filter($this->ofKind($kind), fn (Extension $extension, string $name): bool => $this->applies($name, $extension, $resource), ARRAY_FILTER_USE_BOTH);
    }

    /**
     * @template TKind of ExtensionKind
     *
     * @param  class-string<TKind>  $kind
     * @return array<string, Extension&TKind>
     */
    public function ofKind(string $kind): array
    {
        $extensions = [];

        foreach ($this->all() as $name => $extension) {
            if ($extension instanceof $kind) {
                $extensions[$name] = $extension;
            }
        }

        ksort($extensions, SORT_STRING);

        return $extensions;
    }

    /** @param class-string<Resource> $resource */
    public function appliesTo(string $name, string $resource): bool
    {
        $extension = $this->all()[$name] ?? null;

        return $extension !== null && $this->applies($name, $extension, $resource);
    }

    /**
     * @internal
     *
     * @param  class-string<ExtensionKind>  $kind
     */
    public function hasKind(string $kind): bool
    {
        foreach ($this->all() as $extension) {
            if ($extension instanceof $kind) {
                return true;
            }
        }

        return false;
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
            $built[$name] = $factory->make($class, $name);
        }

        return $built;
    }
}
