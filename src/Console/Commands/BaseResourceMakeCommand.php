<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rest:base-resource', description: 'Create a new base resource class', hidden: true)]
class BaseResourceMakeCommand extends GeneratorCommand
{
    protected $type = 'Base Resource';

    /**
     * Get the stub file for the generator.
     */
    #[\Override]
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/base-resource.stub');
    }

    /**
     * Resolve the fully-qualified path to the stub.
     */
    protected function resolveStubPath(string $stub): string
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
        return $rootNamespace.'\Rest\Resources';
    }
}
