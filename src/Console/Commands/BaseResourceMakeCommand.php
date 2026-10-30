<?php

namespace Lomkit\Rest\Console\Commands;

use Lomkit\Rest\Console\Generation\Stub;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rest:base-resource', description: 'Create a new base resource class', hidden: true)]
class BaseResourceMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Base Resource';

    #[\Override]
    protected function stub(): Stub
    {
        return Stub::BaseResource;
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Resources';
    }
}
