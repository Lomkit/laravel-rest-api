<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class QuickStartCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'rest:quick-start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Demonstrate the app using user related resource registering.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $this->comment('Generating User Resource...');
        $this->callSilent('rest:resource', ['name' => 'UserResource', '--model' => 'User']);

        $this->comment('Generating User Controller...');
        $this->callSilent('rest:controller', ['name' => 'UsersController', '--resource' => 'UserResource']);

        $this->updateUserModelNamespace();
        $this->setAppNamespace();
        $this->updateApiRoutes();

        $this->uncommentApiRoutesFile();

        $this->info('Laravel Rest Api is ready. Type \'php artisan route:list\' to see your new routes !');
    }

    /**
     * Uncomment the API routes file in the application bootstrap file.
     *
     * @return void
     */
    protected function uncommentApiRoutesFile()
    {
        $appBootstrapPath = $this->laravel->bootstrapPath('app.php');

        $content = file_get_contents($appBootstrapPath);

        if (str_contains($content, '// api: ')) {
            (new Filesystem())->replaceInFile(
                '// api: ',
                'api: ',
                $appBootstrapPath,
            );
        } elseif (str_contains($content, 'web: __DIR__.\'/../routes/web.php\',') && !str_contains($content, 'api: __DIR__.\'/../routes/api.php\',')) {
            (new Filesystem())->replaceInFile(
                'web: __DIR__.\'/../routes/web.php\',',
                'web: __DIR__.\'/../routes/web.php\','.PHP_EOL.'        api: __DIR__.\'/../routes/api.php\',',
                $appBootstrapPath,
            );
        } else {
            $this->components->warn('Unable to automatically add API route definition to bootstrap file. API route file should be registered manually if you did not already run `php artisan install:api`.');
        }
    }

    /**
     * Update the User model namespace in the generated files.
     *
     * @return void
     */
    protected function updateUserModelNamespace()
    {
        $resource = app_path('Rest/Resources/UserResource.php');

        if (file_exists(app_path('Models/User.php'))) {
            file_put_contents(
                $resource,
                str_replace('App\Models\Model::class', 'App\Models\User::class', file_get_contents($resource))
            );
        }

        $controller = app_path('Rest/Controllers/UsersController.php');

        if (file_exists(app_path('Models/User.php'))) {
            file_put_contents(
                $controller,
                str_replace('App\Rest\Resources\ModelResource::class', 'App\Rest\Resources\UserResource::class', file_get_contents($controller))
            );
        }
    }

    /**
     * Set the proper application namespace on the installed files.
     *
     * @return void
     */
    protected function setAppNamespace()
    {
        $namespace = $this->laravel->getNamespace();

        $this->setAppNamespaceOn(app_path('Rest/Resources/UserResource.php'), $namespace);
        $this->setAppNamespaceOn(app_path('Rest/Controllers/UsersController.php'), $namespace);
    }

    /**
     * Set the namespace on the given file.
     *
     * @param string $file
     * @param string $namespace
     *
     * @return void
     */
    protected function setAppNamespaceOn($file, $namespace)
    {
        file_put_contents($file, str_replace(
            'App\\',
            $namespace,
            file_get_contents($file)
        ));
    }

    /**
     * Update the api routes file to include the new resource.
     *
     * @return void
     */
    protected function updateApiRoutes()
    {
        $routesPath = base_path('routes/api.php');
        if (!file_exists($routesPath)) {
            file_put_contents($routesPath, '<?php');
        }

        $routeContent = file_get_contents($routesPath);
        $newRoute = "\Lomkit\Rest\Facades\Rest::resource('users', \App\Rest\Controllers\UsersController::class);";

        if (!Str::contains($routeContent, $newRoute)) {
            file_put_contents($routesPath, $routeContent.PHP_EOL.$newRoute);
        }
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param string $stub
     *
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
            ? $customPath
            : __DIR__.str_replace('rest/', '', $stub);
    }
}
