<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Lomkit\Rest\Console\ResolvesStubPath;

class ActionCommand extends GeneratorCommand implements PromptsForMissingInput
{
    use ResolvesStubPath;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'rest:action {name : The name of the action}
        {--path= : The location where the action file should be created}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Action';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new action class';

    public function handle()
    {
        parent::handle();
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/rest/rest-action.stub');
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
        return $rootNamespace.'\Rest\Actions';
    }
}
