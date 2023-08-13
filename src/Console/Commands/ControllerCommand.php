<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Database\Migrations\MigrationCreator;
use Illuminate\Support\Composer;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\ResolvesStubPath;
use Lomkit\Rest\Tests\Support\Rest\Resources\ModelResource;

class ControllerCommand extends GeneratorCommand implements PromptsForMissingInput
{
    use ResolvesStubPath;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'rest:controller {name : The name of the controller}
        {--resource= : The resource to be associated with}
        {--path= : The location where the controller file should be created}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new controller class';

    public function handle()
    {
        parent::handle();

        $this->callSilent('rest:base-controller', [
            'name' => 'Controller',
        ]);
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     * @return string
     */
    protected function buildClass($name)
    {
        $resource = $this->option('resource');

        $resourceNamespace = $this->getResourceNamespace();

        if (is_null($resource)) {
            $resource = $resourceNamespace.'ModelResource';
        } elseif (! Str::startsWith($resource, [
            $resourceNamespace, '\\',
        ])) {
            $resource = $resourceNamespace.$resource;
        }

        $replace = [
            'DummyFullResource' => $resource,
            '{{ namespacedResource }}' => $resource,
            '{{namespacedResource}}' => $resource,
        ];

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/rest/controller.stub');
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Rest\Controllers';
    }

    /**
     * Get the default namespace for the class.
     *
     * @return string
     */
    protected function getResourceNamespace()
    {
        $rootNamespace = $this->laravel->getNamespace();

        return is_dir(app_path('Rest/Resources')) ? $rootNamespace.'Rest\\Resources\\' : $rootNamespace;
    }
}
