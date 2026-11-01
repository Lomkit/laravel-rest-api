<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Lomkit\Rest\Tests\Concerns\RunsTheQuickStart;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Blog\Blog;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\Test;

#[RunTestsInSeparateProcesses]
#[WithConfig('app.url', 'http://localhost')]
#[WithConfig('auth.providers.users.model', 'App\Models\User')]
class QuickStartGuardQuestionTest extends ScaffoldingTestCase
{
    use RunsTheQuickStart;

    private const string PASSPORT_INSTALLED = 'API scaffolding installed. Please add the [Laravel\Passport\HasApiTokens] trait to your User model.';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Blog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->writeModels('App', 'Post', 'Tag', 'User');
        $this->fakeApiInstall();
        $this->runChecksInThisProcess();
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'passport', 'provider' => 'users'])]
    public function it_asks_for_the_guard_before_installing_api_routing_with_its_package(): void
    {
        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsChoice(self::GUARD_QUESTION, 'api', self::GUARDS, strict: true)
            ->expectsConfirmation('API routing is not installed. Run php artisan install:api --passport now? It installs Laravel Passport.', 'yes')
            ->expectsOutputToContain('Published API routes file.')
            ->expectsOutputToContain(self::PASSPORT_INSTALLED)
            ->expectsOutputToContain('created by install:api --passport')
            ->expectsOutputToContain('posts behind auth:api')
            ->assertSuccessful();

        $this->assertSame(str_replace('auth:sanctum', 'auth:api', self::ROUTES_WITH_POSTS), $this->routes());
    }

    #[Test]
    public function it_installs_api_routing_with_laravel_sanctum_when_a_defined_guard_is_chosen(): void
    {
        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsChoice(self::GUARD_QUESTION, 'web', self::GUARDS, strict: true)
            ->expectsConfirmation('API routing is not installed. Run php artisan install:api now? It installs Laravel Sanctum.', 'yes')
            ->expectsOutputToContain('Published API routes file.')
            ->expectsOutputToContain('posts behind auth:web')
            ->assertSuccessful();

        $this->assertStringEndsWith("\nRest::resource('posts', PostsController::class)\n    ->middleware('auth:web');\n", $this->routes());
    }

    #[Test]
    public function it_installs_nothing_when_a_defined_guard_is_chosen_instead_of_laravel_sanctum(): void
    {
        $this->installApiRoutes();

        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsChoice(self::GUARD_QUESTION, 'web', self::GUARDS, strict: true)
            ->doesntExpectOutputToContain('install:api')
            ->expectsOutputToContain('posts behind auth:web')
            ->assertSuccessful();

        $this->assertStringEndsWith("\nRest::resource('posts', PostsController::class)\n    ->middleware('auth:web');\n", $this->routes());
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'passport', 'provider' => 'users'])]
    public function it_asks_for_laravel_passport_when_its_guard_is_chosen(): void
    {
        $this->installApiRoutes();

        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsChoice(self::GUARD_QUESTION, 'api', self::GUARDS, strict: true)
            ->expectsConfirmation('Laravel Passport is not installed. Run php artisan install:api --passport now to install it?', 'yes')
            ->expectsOutputToContain('Running php artisan install:api --passport to install Laravel Passport. It keeps routes/api.php, which already exists.')
            ->expectsOutputToContain(self::PASSPORT_INSTALLED)
            ->assertSuccessful();

        $this->assertStringEndsWith("\nRest::resource('posts', PostsController::class)\n    ->middleware('auth:api');\n", $this->routes());
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'passport', 'provider' => 'users'])]
    public function it_installs_the_package_of_the_chosen_guard_without_confirmation_when_the_option_is_given(): void
    {
        $this->installApiRoutes();

        $this->artisan('rest:quick-start', ['--model' => ['Post'], '--install-api' => true])
            ->expectsChoice(self::GUARD_QUESTION, 'api', self::GUARDS, strict: true)
            ->expectsOutputToContain('Running php artisan install:api --passport to install Laravel Passport.')
            ->expectsOutputToContain(self::PASSPORT_INSTALLED)
            ->assertSuccessful();
    }

    #[Test]
    #[WithConfig('auth.guards.api', ['driver' => 'token', 'provider' => 'users'])]
    #[WithConfig('auth.guards.admin', ['driver' => 'session', 'provider' => 'users'])]
    public function it_offers_every_defined_guard_with_the_package_it_needs(): void
    {
        $this->installApiRoutes();

        $this->artisan('rest:quick-start', ['--model' => ['Post']])
            ->expectsChoice(self::GUARD_QUESTION, 'admin', ['sanctum' => 'sanctum (Laravel Sanctum)', 'api' => 'api', 'web' => 'web', 'admin' => 'admin'], strict: true)
            ->doesntExpectOutputToContain('install:api')
            ->expectsOutputToContain('posts behind auth:admin')
            ->assertSuccessful();
    }

    private function routes(): string
    {
        return (string) file_get_contents(base_path('routes/api.php'));
    }
}
