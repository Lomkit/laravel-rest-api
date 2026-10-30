<?php

namespace Lomkit\Rest\Console\Commands;

use Lomkit\Rest\Console\Generation\Stub;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rest:action', description: 'Create a new action class')]
class ActionMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Action';

    #[\Override]
    protected function stub(): Stub
    {
        return Stub::Action;
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Actions';
    }
}
