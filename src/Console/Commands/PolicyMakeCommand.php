<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Config\Repository;
use Lomkit\Rest\Console\Generation\ClassReference;
use Lomkit\Rest\Console\Generation\ModelTable;
use Lomkit\Rest\Console\Generation\PolicyBlueprint;
use Lomkit\Rest\Console\Generation\Stub;
use Lomkit\Rest\Console\Generation\UserModel;
use Lomkit\Rest\Exceptions\InvalidClassReferenceException;
use Lomkit\Rest\Exceptions\InvalidModelTableException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\suggest;

#[AsCommand(name: 'rest:policy', description: 'Create a new read-only policy class')]
class PolicyMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Policy';

    private string $model;

    private PolicyBlueprint $blueprint;

    #[\Override]
    protected function stub(): Stub
    {
        return Stub::Policy;
    }

    #[\Override]
    protected function interact(InputInterface $input, OutputInterface $output): void
    {
        parent::interact($input, $output);

        if ($input->getOption('model') !== null) {
            return;
        }

        $model = suggest('What model should this policy authorize?', $this->findAvailableModels());

        if ($model !== '') {
            $input->setOption('model', $model);
        }
    }

    #[\Override]
    protected function prepare(): void
    {
        $reference = $this->modelReference();

        if ($reference === null) {
            $this->fail('No model was given or found for this policy. Pass --model to set it.');
        }

        try {
            $this->model = ModelTable::of($reference)->class;
        } catch (InvalidModelTableException $exception) {
            $this->fail($exception->getMessage());
        }

        $user = UserModel::configured($this->laravel->make(Repository::class), $this->rootNamespace());
        $this->blueprint = PolicyBlueprint::for($this->qualifyClass($this->getNameInput()), $this->model, $user);
        $this->reportTheRegisteredPolicy();
    }

    #[\Override]
    protected function replacements(string $name): array
    {
        return $this->blueprint->replacements();
    }

    #[\Override]
    protected function keepsAnotherClass(): bool
    {
        return $this->registeredPolicy() !== null && $this->option('force') !== true;
    }

    #[\Override]
    protected function afterWritten(): void
    {
        $policy = $this->registeredPolicy();

        if ($policy === null) {
            return;
        }

        $this->components->warn("Gate still uses the policy [{$policy}] for the model [{$this->model}]. Register [{$this->qualifyClass($this->getNameInput())}] with Gate::policy() to use it.");
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['model', null, InputOption::VALUE_REQUIRED, 'The model that the policy authorizes'],
        ];
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Policies';
    }

    private function reportTheRegisteredPolicy(): void
    {
        $policy = $this->registeredPolicy();

        if ($policy === null || $this->option('force') === true) {
            return;
        }

        $this->components->info("The model [{$this->model}] already has the policy [{$policy}].");
    }

    private function registeredPolicy(): ?string
    {
        $policy = $this->laravel->make(Gate::class)->getPolicyFor($this->model);

        if (! is_object($policy) || $policy::class === $this->qualifyClass($this->getNameInput())) {
            return null;
        }

        return $policy::class;
    }

    private function modelReference(): ?ClassReference
    {
        $model = $this->option('model');
        $namespace = is_dir($this->laravel->path('Models')) ? $this->rootNamespace().'Models\\' : $this->rootNamespace();

        try {
            if (is_string($model) && $model !== '') {
                return ClassReference::fromInput($model, $this->rootNamespace(), $namespace);
            }

            return ClassReference::guessPolicyModel($this->relativeName(), $namespace);
        } catch (InvalidClassReferenceException $exception) {
            $this->fail($exception->getMessage());
        }
    }
}
