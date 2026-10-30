<?php

namespace Lomkit\Rest\Console\Commands;

use Lomkit\Rest\Console\Generation\Stub;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rest:instruction', description: 'Create a new instruction class')]
class InstructionMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Instruction';

    #[\Override]
    protected function stub(): Stub
    {
        return Stub::Instruction;
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Instructions';
    }
}
