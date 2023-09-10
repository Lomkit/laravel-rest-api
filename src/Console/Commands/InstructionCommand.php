<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Lomkit\Rest\Console\ResolvesStubPath;

class InstructionCommand extends GeneratorCommand implements PromptsForMissingInput
{
    use ResolvesStubPath;

    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'rest:instruction {name : The name of instruction action}
        {--path= : The location where the instruction file should be created}';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Instruction';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new instruction class';

    /**
     * Handle the console command.
     *
     * @return void
     */
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
        return parent::buildClass($name);
    }

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub()
    {
        return $this->resolveStubPath('/stubs/rest/instruction.stub');
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
        return $rootNamespace.'\Rest\Instructions';
    }
}
