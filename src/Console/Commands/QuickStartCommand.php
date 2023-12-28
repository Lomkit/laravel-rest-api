<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\Command;
use Lomkit\Rest\Console\ResolvesStubPath;

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

        if (file_exists(app_path('Models/User.php'))) {
            file_put_contents(
                app_path('Rest/Resources/UserResource.php'),
                str_replace('App\User::class', 'App\Models\User::class', file_get_contents(app_path('Rest/Resources/UserResource.php')))
            );
        }

        $this->comment('Generating User Controller...');
        $this->callSilent('rest:controller', ['name' => 'UsersController']);
        copy($this->resolveStubPath('/stubs/user-controller.stub'), app_path('Rest/Controllers/UsersController.php'));

        if (file_exists(app_path('Models/User.php'))) {
            file_put_contents(
                app_path('Rest/Controllers/UsersController.php'),
                str_replace('App\User::class', 'App\Models\User::class', file_get_contents(app_path('Rest/Controllers/UsersController.php')))
            );
        }

        $this->setAppNamespace();

        if (file_exists(base_path('routes/api.php'))) {
            file_put_contents(
                base_path('routes/api.php'),
                file_get_contents(base_path('routes/api.php')).
                '\Lomkit\Rest\Facades\Rest::resource(\'users\', \App\Rest\Controllers\UsersController::class);'
            );
        }

        $this->info('Laravel Rest Api is ready. Type \'php artisan route:list\' to see your new routes !');
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
}
