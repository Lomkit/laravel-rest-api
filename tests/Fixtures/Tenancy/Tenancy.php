<?php

namespace Lomkit\Rest\Tests\Fixtures\Tenancy;

final class Tenancy
{
    public const string CENTRAL = 'central';

    public static ?string $tenant = null;

    /** @var array<string, string> */
    public static array $connections = [];

    private static ?string $application = null;

    public static function initialize(?string $tenant): void
    {
        self::$application ??= config('database.default');
        self::$tenant = $tenant;

        config(['database.default' => $tenant === null ? self::CENTRAL : self::$connections[$tenant]]);
    }

    public static function forget(): void
    {
        if (self::$application !== null) {
            config(['database.default' => self::$application]);
        }

        self::$tenant = null;
        self::$connections = [];
        self::$application = null;
    }
}
