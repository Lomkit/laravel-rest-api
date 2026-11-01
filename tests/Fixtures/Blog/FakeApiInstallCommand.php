<?php

namespace Lomkit\Rest\Tests\Fixtures\Blog;

use Illuminate\Console\Command;
use Illuminate\Foundation\Console\ApiInstallCommand;
use ReflectionClass;

class FakeApiInstallCommand extends Command
{
    protected $signature = 'install:api {--composer=global} {--force} {--passport} {--without-migration-prompt}';

    public function __construct(private readonly bool $publishesRoutes = true, private readonly bool $succeeds = true)
    {
        parent::__construct();
    }

    public static function routesStub(): string
    {
        return dirname((string) (new ReflectionClass(ApiInstallCommand::class))->getFileName()).'/stubs/api-routes.stub';
    }

    public function handle(): int
    {
        if (! $this->succeeds) {
            return self::FAILURE;
        }

        $package = $this->option('passport') === true ? 'Passport' : 'Sanctum';

        if (file_exists(base_path('routes/api.php')) && $this->option('force') !== true) {
            $this->components->error('API routes file already exists.');
        } elseif ($this->publishesRoutes) {
            $this->components->info('Published API routes file.');
            $routes = (string) file_get_contents(self::routesStub());
            file_put_contents(base_path('routes/api.php'), $package === 'Passport' ? str_replace('auth:sanctum', 'auth:api', $routes) : $routes);
        }

        $this->components->info("API scaffolding installed. Please add the [Laravel\\{$package}\\HasApiTokens] trait to your User model.");

        return self::SUCCESS;
    }
}
