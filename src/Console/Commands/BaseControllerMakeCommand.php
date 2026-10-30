<?php

namespace Lomkit\Rest\Console\Commands;

use Lomkit\Rest\Console\Generation\Stub;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rest:base-controller', description: 'Create a new base controller class', hidden: true)]
class BaseControllerMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Base Controller';

    #[\Override]
    protected function stub(): Stub
    {
        return Stub::BaseController;
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Controllers';
    }
}
