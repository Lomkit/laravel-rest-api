<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Contracts\Config\Repository;

enum ApiPackage: string
{
    case Sanctum = 'Laravel Sanctum';
    case Passport = 'Laravel Passport';

    private const string TOKEN = 'php artisan tinker --execute="echo %s::first()->createToken(\'cli\')->%s;"';

    public function installer(): string
    {
        return match ($this) {
            self::Sanctum => 'install:api',
            self::Passport => 'install:api --passport',
        };
    }

    /** @return array<string, true> */
    public function installOptions(): array
    {
        return match ($this) {
            self::Sanctum => [],
            self::Passport => ['--passport' => true],
        };
    }

    public function isInstalled(Repository $config): bool
    {
        return match ($this) {
            self::Sanctum => $config->get('auth.guards.sanctum') !== null,
            self::Passport => class_exists('Laravel\Passport\Passport'),
        };
    }

    public function guardDriver(): ?string
    {
        return match ($this) {
            self::Sanctum => null,
            self::Passport => 'passport',
        };
    }

    public function tokensTrait(): string
    {
        return match ($this) {
            self::Sanctum => 'Laravel\Sanctum\HasApiTokens',
            self::Passport => 'Laravel\Passport\HasApiTokens',
        };
    }

    public function tokenCommand(string $user): string
    {
        return sprintf(self::TOKEN, $user, match ($this) {
            self::Sanctum => 'plainTextToken',
            self::Passport => 'accessToken',
        });
    }
}
