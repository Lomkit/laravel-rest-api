<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Lomkit\Rest\Console\ResolvesStubPath;

class DocumentationProviderCommand extends GeneratorCommand implements PromptsForMissingInput
{
    use ResolvesStubPath;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'rest:documentation-provider {name=RestDocumentationServiceProvider : The name of the service provider}
        {--path= : The location where the service provider file should be created}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Service provider';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create the documentation service provider class';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/rest/rest-documentation-service-provider.stub');
    }

    /**
     * Get the path where the action file should be created.
     *
     * @param string $name
     *
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
     * Get the default namespace for the class.
     *
     * @param string $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace)
    {
        return $rootNamespace.'\Providers';
    }

    /**
     * Get the default namespace for the class.
     *
     * @return string
     */
    protected function getResourceNamespace()
    {
        $rootNamespace = $this->laravel->getNamespace();

        return is_dir(app_path('Providers')) ? $rootNamespace.'Providers\\' : $rootNamespace;
    }
}
