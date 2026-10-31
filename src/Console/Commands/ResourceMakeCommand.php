<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Database\QueryException;
use Lomkit\Rest\Console\Generation\BaseClass;
use Lomkit\Rest\Console\Generation\ClassReference;
use Lomkit\Rest\Console\Generation\ImportSet;
use Lomkit\Rest\Console\Generation\ModelTable;
use Lomkit\Rest\Console\Generation\RelationMapping;
use Lomkit\Rest\Console\Generation\ResourceBlueprint;
use Lomkit\Rest\Console\Generation\Stub;
use Lomkit\Rest\Exceptions\InvalidClassReferenceException;
use Lomkit\Rest\Exceptions\InvalidModelTableException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Formatter\OutputFormatter;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\suggest;

#[AsCommand(name: 'rest:resource', description: 'Create a new resource class')]
class ResourceMakeCommand extends RestGeneratorCommand
{
    protected $type = 'Resource';

    private ?ClassReference $model = null;

    private ResourceBlueprint $blueprint;

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

        if ($this->namesTheBaseClass()) {
            return;
        }

        if ($input->getOption('model') === null) {
            $this->askForTheModel($input);
        }

        if ($input->getOption('generate') !== true) {
            $this->askToGenerate($input);
        }
    }

    #[\Override]
    protected function prepare(): void
    {
        try {
            $this->model = $this->modelReference();
            $this->blueprint = $this->blueprint();
        } catch (InvalidClassReferenceException|InvalidModelTableException $exception) {
            $this->fail($exception->getMessage());
        }
    }

    #[\Override]
    protected function replacements(string $name): array
    {
        return $this->blueprint->replacements();
    }

    #[\Override]
    protected function afterWritten(): void
    {
        $this->warnAboutReference($this->model, 'model');

        if ($this->option('generate') !== true) {
            return;
        }

        $this->components->twoColumnDetail(class_basename($this->qualifyClass($this->getNameInput())), $this->blueprint->summary());

        foreach ($this->blueprint->skipped() as $relation) {
            $this->line('  Skipped: '.OutputFormatter::escape($relation));
        }

        foreach ($this->blueprint->notes() as $note) {
            $this->line('  Note: '.OutputFormatter::escape($note));
        }
    }

    #[\Override]
    protected function getOptions(): array
    {
        return [
            ...parent::getOptions(),
            ['model', null, InputOption::VALUE_REQUIRED, 'The model associated with this resource'],
            ['generate', 'G', InputOption::VALUE_NONE, 'Generate the fields and relations from the table of the model'],
        ];
    }

    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Resources';
    }

    private function askForTheModel(InputInterface $input): void
    {
        $model = suggest('What model should this resource rely on? (Optional)', $this->findAvailableModels());

        if ($model !== '') {
            $input->setOption('model', $model);
        }
    }

    private function askToGenerate(InputInterface $input): void
    {
        $source = $this->sourceWithTable();

        if ($source === null) {
            return;
        }

        $input->setOption('generate', confirm("Generate the fields and relations from the {$source->name()} table?", default: true));
    }

    private function sourceWithTable(): ?ModelTable
    {
        try {
            $source = ModelTable::find($this->modelReference());

            return $source?->exists() === true ? $source : null;
        } catch (InvalidClassReferenceException|QueryException) {
            return null;
        }
    }

    private function blueprint(): ResourceBlueprint
    {
        $class = $this->qualifyClass($this->getNameInput());
        $imports = ImportSet::forResource($class, $this->baseClass()->fqcn);

        if ($this->option('generate') !== true) {
            return ResourceBlueprint::withKeyOnly($imports, $this->model?->fqcn(), ModelTable::find($this->model)?->model->getKeyName() ?? 'id');
        }

        $source = ModelTable::of($this->model);
        $relations = RelationMapping::using($this->resourceIndex()->withPlanned($class, $source->class), $this->modelNamespace());

        return ResourceBlueprint::fromTable($imports, $source, $relations);
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
