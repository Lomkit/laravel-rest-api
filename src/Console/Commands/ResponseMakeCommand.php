<?php

namespace Lomkit\Rest\Console\Commands;

use Lomkit\Rest\Console\Generation\Stub;
use Symfony\Component\Console\Attribute\AsCommand;

#[AsCommand(name: 'rest:response', description: 'Create a new response class')]
class ResponseMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Response';

    #[\Override]
    protected function stub(): Stub
    {
        return Stub::Response;
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Responses';
    }
}
