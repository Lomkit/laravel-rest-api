<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\ResolvesStubPath;

class ResourceCommand extends GeneratorCommand implements PromptsForMissingInput
{
    use ResolvesStubPath;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'rest:resource {name : The name of the resource}
        {--model= : The model to be associated with}
        {--path= : The location where the resource file should be created}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Resource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new resource class';

    public function handle()
    {
        parent::handle();

        if (is_null($this->option('path'))) {
            $this->callSilent('rest:base-resource', [
                'name' => 'Resource',
            ]);
        }
    }

    /**
     * Get the path where the action file should be created.
     *
     * @param  string  $name
     * @return string
     */
    protected function getPath($name)
    {
        if (!is_null($this->option('path'))) {
            return $this->option('path').'/'.$this->argument('name').'.php';
        }

        return parent::getPath($name);
    }

    /**
     * Build the class with the given name.
     *
     * @param string $name
     *
     * @return string
     */
    protected function buildClass($name)
    {
        $model = $this->option('model') ?? 'App\\Models\\Model';

        $modelNamespace = $this->getModelNamespace();

        if (is_null($model)) {
            $resource = $modelNamespace.str_replace('/', '\\', $this->argument('name'));
        } elseif (!Str::startsWith($model, [
            $modelNamespace, '\\',
        ])) {
            $model = $modelNamespace.$model;
        }

        $replace = [
            'DummyFullModel'        => $model,
            '{{ namespacedModel }}' => $model,
            '{{namespacedModel}}'   => $model,
        ];

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/rest/resource.stub');
    }

    /**
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Rest\Resources';
    }

    /**
     * Get the default namespace for the class.
     *
     * @return string
     */
    protected function getModelNamespace()
    {
        $rootNamespace = $this->laravel->getNamespace();

        return is_dir(app_path('Models')) ? $rootNamespace.'Models\\' : $rootNamespace;
    }
}
