<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\Generation\BaseClass;
use Lomkit\Rest\Console\Generation\ClassReference;
use Lomkit\Rest\Console\Generation\ResourceIndex;
use Lomkit\Rest\Console\Generation\Stub;
use Symfony\Component\Console\Input\InputOption;

abstract class RestGeneratorCommand extends GeneratorCommand
{
    abstract protected function stub(): Stub;

    #[\Override]
    final public function handle(): ?bool
    {
        $this->rejectTheBaseClassName();
        $this->prepare();

        if (parent::handle() === false) {
            return false;
        }

        $this->ensureBaseClass();
        $this->afterWritten();

        return null;
    }

    protected function baseClass(): ?BaseClass
    {
        return null;
    }

    protected function prepare(): void {}

    protected function afterWritten(): void {}

    /** @return array<string, string|null> */
    protected function replacements(string $name): array
    {
        return [];
    }

    #[\Override]
    protected function getStub(): string
    {
        return $this->stub()->path();
    }

    #[\Override]
    protected function buildClass($name): string
    {
        return $this->stub()->fill([
            '{{ namespace }}' => $this->getNamespace($name),
            '{{ rootNamespace }}' => $this->rootNamespace(),
            '{{ class }}' => class_basename($name),
            ...$this->baseClassImport($name),
            ...$this->replacements($name),
        ]);
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            ['force', 'f', InputOption::VALUE_NONE, 'Create the class even if the '.strtolower($this->type).' already exists'],
        ];
    }

    protected function relativeName(): string
    {
        $name = $this->qualifyClass($this->getNameInput());
        $namespace = $this->getDefaultNamespace(trim($this->rootNamespace(), '\\')).'\\';

        return str_starts_with($name, $namespace) ? Str::after($name, $namespace) : class_basename($name);
    }

    protected function warnAboutReference(?ClassReference $reference, string $option): void
    {
        if ($reference === null) {
            $this->components->warn('No '.$option.' was given or found for this '.strtolower($this->type).". Pass --{$option} to set it.");

            return;
        }

        if (! $reference->exists()) {
            $this->components->warn("The {$option} [{$reference->fqcn()}] does not exist.");
        }
    }

    protected function namesTheBaseClass(): bool
    {
        return $this->baseClass()?->isNamedLike($this->getNameInput()) === true;
    }

    protected function resourceIndex(): ResourceIndex
    {
        return new ResourceIndex($this->laravel->path('Rest/Resources'), $this->rootNamespace().'Rest\Resources\\');
    }

    private function rejectTheBaseClassName(): void
    {
        $base = $this->baseClass();

        if ($base === null || ! $this->namesTheBaseClass()) {
            return;
        }

        $this->fail("The name \"{$this->getNameInput()}\" is reserved for the base class [{$base->fqcn}].");
    }

    /** @return array<string, null> */
    private function baseClassImport(string $name): array
    {
        $base = $this->baseClass();

        if ($base === null || ! $base->sharesNamespaceWith($name)) {
            return [];
        }

        return ["use {$base->fqcn};" => null];
    }

    private function ensureBaseClass(): void
    {
        $base = $this->baseClass();

        if ($base === null || $this->files->exists($this->getPath($base->fqcn))) {
            return;
        }

        $this->call($base->command, ['name' => $base->fqcn]);
    }
}
