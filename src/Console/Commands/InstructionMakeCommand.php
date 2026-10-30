<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rest:instruction', description: 'Create a new instruction class')]
class InstructionMakeCommand extends GeneratorCommand
{
    protected $type = 'Instruction';

    /**
     * Get the stub file for the generator.
     */
    #[\Override]
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/instruction.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     */
    protected function resolveStubPath($stub): string
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
                        ? $customPath
                        : __DIR__.$stub;
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Instructions';
    }
}
