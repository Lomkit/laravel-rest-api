<?php

namespace Lomkit\Rest\Console\Commands;

use Lomkit\Rest\Console\Generation\BaseClass;
use Lomkit\Rest\Console\Generation\ClassReference;
use Lomkit\Rest\Console\Generation\Stub;
use Lomkit\Rest\Exceptions\InvalidClassReferenceException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\suggest;

#[AsCommand(name: 'rest:resource', description: 'Create a new resource class')]
class ResourceMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Resource';

    private ?ClassReference $model = null;

    #[\Override]
    protected function stub(): Stub
    {
        return Stub::Resource;
    }

    #[\Override]
    protected function baseClass(): BaseClass
    {
        return new BaseClass($this->rootNamespace().'Rest\Resources\Resource', 'rest:base-resource');
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        if ($input->getOption('model') !== null) {
            return;
        }

        $model = suggest('What model should this resource rely on? (Optional)', $this->findAvailableModels());

        if ($model !== '') {
            $input->setOption('model', $model);
        }
    }

    #[\Override]
    protected function prepare(): void
    {
        try {
            $this->model = $this->modelReference();
        } catch (InvalidClassReferenceException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    #[\Override]
    protected function replacements(string $name): array
    {
        $import = $this->model?->import([class_basename($name), 'Resource', 'ID', 'RestRequest'], 'Model');

        return [
            '{{ namespacedModel }}' => $import?->statement(),
            '{{ model }}' => $import?->name(),
        ];
    }

    #[\Override]
    protected function afterWritten(): void
    {
        $this->warnAboutReference($this->model, 'model');
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['model', null, InputOption::VALUE_REQUIRED, 'The model associated with this resource'],
        ];
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Resources';
    }

    private function modelReference(): ?ClassReference
    {
        $model = $this->option('model');

        if (is_string($model) && $model !== '') {
            return ClassReference::fromInput($model, $this->rootNamespace(), $this->modelNamespace());
        }

        return ClassReference::guessModel($this->relativeName(), $this->modelNamespace());
    }

    private function modelNamespace(): string
    {
        return is_dir($this->laravel->path('Models')) ? $this->rootNamespace().'Models\\' : $this->rootNamespace();
    }
}
