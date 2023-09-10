<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Lomkit\Rest\Console\ResolvesStubPath;

class ResponseCommand extends GeneratorCommand implements PromptsForMissingInput
{
    use ResolvesStubPath;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'rest:response {name : The name of the response}
        {--path= : The location where the response file should be created}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Response';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new response class';

    public function handle()
    {
        parent::handle();
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
        $replace = [];

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
        return $this->resolveStubPath('/stubs/rest/response.stub');
    }

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
        return $rootNamespace.'\Rest\Responses';
    }
}
