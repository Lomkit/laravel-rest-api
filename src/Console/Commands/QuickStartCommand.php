<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\Command;
use Lomkit\Rest\Console\ResolvesStubPath;
use Illuminate\Support\Str;

class QuickStartCommand extends Command
{
    use ResolvesStubPath;

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
        $this->callSilent('rest:resource', ['name' => 'UserResource']);
        copy($this->resolveStubPath('/stubs/user-resource.stub'), app_path('Rest/Resources/UserResource.php'));

        $this->comment('Generating User Controller...');
        $this->callSilent('rest:controller', ['name' => 'UsersController']);
        copy($this->resolveStubPath('/stubs/user-controller.stub'), app_path('Rest/Controllers/UsersController.php'));

        $this->updateUserModelNamespace();
        $this->setAppNamespace();
        $this->updateApiRoutes();

        $this->info('Laravel Rest Api is ready. Type \'php artisan route:list\' to see your new routes !');
    }

    /**
     * Update the User model namespace in the generated files.
     *
     * @return void
     */
    protected function updateUserModelNamespace()
    {
        $files = [
            app_path('Rest/Resources/UserResource.php'),
            app_path('Rest/Controllers/UsersController.php')
        ];

        foreach ($files as $file) {
            if (file_exists(app_path('Models/User.php'))) {
                file_put_contents(
                    $file,
                    str_replace('App\User::class', 'App\Models\User::class', file_get_contents($file))
                );
            }
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
     * @param  string  $file
     * @param  string  $namespace
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
        if (! file_exists($routesPath)) {
            return;
        }

        $routeContent = file_get_contents($routesPath);
        $newRoute = "\Lomkit\Rest\Facades\Rest::resource('users', \App\Rest\Controllers\UsersController::class);";

        if (! Str::contains($routeContent, $newRoute)) {
            file_put_contents($routesPath, $routeContent.PHP_EOL.$newRoute);
        }
    }
}
