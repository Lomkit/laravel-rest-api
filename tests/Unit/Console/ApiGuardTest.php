<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Illuminate\Config\Repository;
use Lomkit\Rest\Console\QuickStart\ApiGuard;
use Lomkit\Rest\Console\QuickStart\ApiPackage;
use Lomkit\Rest\Console\QuickStart\GuardKind;
use Lomkit\Rest\Exceptions\UndefinedGuardException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ApiGuardTest extends TestCase
{
    private const array WEB = ['driver' => 'session', 'provider' => 'users'];

    /** @param  array<string, mixed>  $guards */
    #[Test]
    #[DataProvider('guards')]
    public function it_reads_the_kind_of_a_guard_from_its_name_and_definition(string $name, array $guards, GuardKind $kind, string $label): void
    {
        $guard = ApiGuard::named($name, $this->config($guards));

        $this->assertSame($kind, $guard->kind);
        $this->assertSame($label, $guard->label());
        $this->assertSame("auth:{$name}", $guard->middleware());
    }

    /** @return array<string, array{string, array<string, mixed>, GuardKind, string}> */
    public static function guards(): array
    {
        return [
            'sanctum, defined or not' => ['sanctum', ['web' => self::WEB], GuardKind::Sanctum, 'sanctum (Laravel Sanctum)'],
            'api, not defined' => ['api', ['web' => self::WEB], GuardKind::Passport, 'api (Laravel Passport)'],
            'api with the passport driver' => ['api', ['api' => ['driver' => 'passport', 'provider' => 'users']], GuardKind::Passport, 'api (Laravel Passport)'],
            'api with another driver' => ['api', ['api' => ['driver' => 'token', 'provider' => 'users']], GuardKind::Defined, 'api'],
            'another defined guard' => ['web', ['web' => self::WEB], GuardKind::Defined, 'web'],
        ];
    }

    #[Test]
    #[DataProvider('undefinedGuards')]
    public function it_refuses_a_guard_that_is_not_defined(string $name): void
    {
        $this->expectException(UndefinedGuardException::class);
        $this->expectExceptionMessage("The [{$name}] guard is not defined in config/auth.php.");

        ApiGuard::named($name, $this->config(['web' => self::WEB]));
    }

    #[Test]
    #[DataProvider('undefinedGuards')]
    public function it_finds_no_guard_that_is_not_defined(string $name): void
    {
        $this->assertNull(ApiGuard::find($name, $this->config(['web' => self::WEB])));
    }

    /** @return array<string, array{string}> */
    public static function undefinedGuards(): array
    {
        return [
            'a missing guard' => ['admin'],
            'a configuration path' => ['web.driver'],
            'an empty name' => [''],
        ];
    }

    #[Test]
    public function it_offers_sanctum_then_api_then_the_other_defined_guards(): void
    {
        $config = $this->config(['web' => self::WEB, 'sanctum' => self::WEB, 'admin' => self::WEB]);

        $this->assertSame(
            ['sanctum' => 'sanctum (Laravel Sanctum)', 'api' => 'api (Laravel Passport)', 'web' => 'web', 'admin' => 'admin'],
            ApiGuard::choices($config),
        );
    }

    #[Test]
    public function it_needs_an_install_only_while_the_package_of_its_guard_is_missing(): void
    {
        $withoutSanctum = $this->config(['web' => self::WEB]);
        $withSanctum = $this->config(['web' => self::WEB, 'sanctum' => self::WEB]);

        $this->assertTrue(ApiGuard::named('sanctum', $withoutSanctum)->needsInstall($withoutSanctum));
        $this->assertFalse(ApiGuard::named('sanctum', $withSanctum)->needsInstall($withSanctum));
        $this->assertFalse(ApiGuard::named('web', $withoutSanctum)->needsInstall($withoutSanctum));
    }

    #[Test]
    public function it_installs_laravel_sanctum_to_create_the_routes_of_a_defined_guard(): void
    {
        $this->assertSame(ApiPackage::Sanctum, GuardKind::Sanctum->installs());
        $this->assertSame(ApiPackage::Passport, GuardKind::Passport->installs());
        $this->assertSame(ApiPackage::Sanctum, GuardKind::Defined->installs());
        $this->assertNull(GuardKind::Defined->package());
    }

    #[Test]
    public function it_describes_the_install_and_the_token_of_each_package(): void
    {
        $this->assertSame(['install:api', [], 'Laravel\Sanctum\HasApiTokens', null], [ApiPackage::Sanctum->installer(), ApiPackage::Sanctum->installOptions(), ApiPackage::Sanctum->tokensTrait(), ApiPackage::Sanctum->guardDriver()]);
        $this->assertSame(['install:api --passport', ['--passport' => true], 'Laravel\Passport\HasApiTokens', 'passport'], [ApiPackage::Passport->installer(), ApiPackage::Passport->installOptions(), ApiPackage::Passport->tokensTrait(), ApiPackage::Passport->guardDriver()]);
        $this->assertSame('php artisan tinker --execute="echo App\Models\User::first()->createToken(\'cli\')->plainTextToken;"', ApiPackage::Sanctum->tokenCommand('App\Models\User'));
        $this->assertSame('php artisan tinker --execute="echo App\Models\User::first()->createToken(\'cli\')->accessToken;"', ApiPackage::Passport->tokenCommand('App\Models\User'));
    }

    /** @param  array<string, mixed>  $guards */
    private function config(array $guards): Repository
    {
        return new Repository(['auth' => ['guards' => $guards]]);
    }
}
