<?php

namespace Lomkit\Rest\Console\Commands;

use Lomkit\Rest\Console\Generation\BaseClass;
use Lomkit\Rest\Console\Generation\ClassReference;
use Lomkit\Rest\Console\Generation\ImportSet;
use Lomkit\Rest\Console\Generation\Stub;
use Lomkit\Rest\Exceptions\InvalidClassReferenceException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\suggest;

#[AsCommand(name: 'rest:controller', description: 'Create a new controller class')]
class ControllerMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Controller';

    private ?ClassReference $resource = null;

    #[\Override]
    protected function stub(): Stub
    {
        return Stub::Controller;
    }

    #[\Override]
    protected function baseClass(): BaseClass
    {
        return new BaseClass($this->rootNamespace().'Rest\Controllers\Controller', 'rest:base-controller');
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        if ($this->namesTheBaseClass() || $input->getOption('resource') !== null) {
            return;
        }

        $resource = suggest('What resource should this controller rely on? (Optional)', $this->resourceIndex()->names());

        if ($resource !== '') {
            $input->setOption('resource', $resource);
        }
    }

    #[\Override]
    protected function prepare(): void
    {
        try {
            $this->resource = $this->resourceReference();
        } catch (InvalidClassReferenceException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    #[\Override]
    protected function replacements(string $name): array
    {
        $resource = $this->resource?->fqcn();

        if ($resource === null) {
            return ['{{ namespacedResource }}' => null, '{{ resource }}' => null];
        }

        $imports = ImportSet::forController($name, $this->baseClass()->fqcn)->with($resource, 'Resource');

        return [
            '{{ namespacedResource }}' => $imports->statement($resource),
            '{{ resource }}' => $imports->name($resource),
        ];
    }

    #[\Override]
    protected function afterWritten(): void
    {
        $this->warnAboutReference($this->resource, 'resource');

        if ($this->resource !== null) {
            return;
        }

        $this->components->warn('Set $resource before you register this controller with Rest::resource(): the routes cannot register without it.');
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['resource', null, InputOption::VALUE_REQUIRED, 'The resource associated with this controller'],
        ];
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Controllers';
    }

    private function resourceReference(): ?ClassReference
    {
        $resource = $this->option('resource');

        if (is_string($resource) && $resource !== '') {
            return ClassReference::fromInput($resource, $this->rootNamespace(), $this->resourceNamespace());
        }

        return ClassReference::guessResource($this->relativeName(), $this->resourceNamespace());
    }

    private function resourceNamespace(): string
    {
        return $this->rootNamespace().'Rest\Resources\\';
    }
}
