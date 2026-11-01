<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Contracts\Config\Repository;
use Lomkit\Rest\Exceptions\UndefinedGuardException;

final readonly class ApiGuard
{
    public const string SANCTUM = 'sanctum';

    public const string PASSPORT = 'api';

    private function __construct(public string $name, public GuardKind $kind) {}

    public static function named(string $name, Repository $config): self
    {
        return self::find($name, $config) ?? throw UndefinedGuardException::for($name);
    }

    public static function find(string $name, Repository $config): ?self
    {
        $kind = GuardKind::of($name, self::guards($config)[$name] ?? null);

        return $kind === null ? null : new self($name, $kind);
    }

    /** @return array<string, string> */
    public static function choices(Repository $config): array
    {
        $names = array_unique([self::SANCTUM, self::PASSPORT, ...array_map(strval(...), array_keys(self::guards($config)))]);
        $choices = [];

        foreach ($names as $name) {
            $choices[$name] = self::named($name, $config)->label();
        }

        return $choices;
    }

    public function middleware(): string
    {
        return "auth:{$this->name}";
    }

    public function label(): string
    {
        $package = $this->kind->package();

        return $package === null ? $this->name : "{$this->name} ({$package->value})";
    }

    public function needsInstall(Repository $config): bool
    {
        return $this->kind->package()?->isInstalled($config) === false;
    }

    public function isDefined(Repository $config): bool
    {
        return isset(self::guards($config)[$this->name]);
    }

    /** @return array<array-key, mixed> */
    private static function guards(Repository $config): array
    {
        $guards = $config->get('auth.guards');

        return is_array($guards) ? $guards : [];
    }
}
