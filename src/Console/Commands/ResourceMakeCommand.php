<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\suggest;

#[AsCommand(name: 'rest:resource', description: 'Create a new resource class')]
class ResourceMakeCommand extends GeneratorCommand
{
    protected $type = 'Resource';

    /**
     * Get the stub file for the generator.
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/resource.stub');
    }

    /**
     * Execute the console command.
     *
     * @throws FileNotFoundException
     */
    public function handle(): ?bool
    {
        $this->callSilent('rest:base-resource', [
            'name' => 'Resource',
        ]);

        return parent::handle();
    }

    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param  string  $stub
     * @return string
     */
    protected function resolveStubPath($stub)
    {
        return file_exists($customPath = $this->laravel->basePath(trim($stub, '/')))
                        ? $customPath
                        : __DIR__.$stub;
    }

    /**
     * Build the class with the given name.
     *
     * @param  string  $name
     *
     * @throws FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $rootNamespace = $this->rootNamespace();

        $replace = [];

        if ($this->option('model')) {
            $replace = $this->buildModelReplacements($replace);
        } else {
            $replace = array_merge(
                $replace,
                [
                    '{{ namespacedModel }}' => 'Model',
                ]
            );
        }

        $baseResourceExists = file_exists($this->getPath("{$rootNamespace}Rest\Resources\Resource"));

        if (! $baseResourceExists) {
            $replace["use {$rootNamespace}Rest\Resources\Resource;\n"] = "use Lomkit\\Rest\\Http\\Resource;\n";
        }

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * @param  array<string, string>  $replace
     * @return array<string, string>
     */
    protected function buildModelReplacements(array $replace): array
    {
        $modelClass = $this->parseModel($this->option('model'));

        return array_merge($replace, [
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}' => $modelClass,
        ]);
    }

    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        $model = suggest('What model should this resource rely on? (Optional)', $this->findAvailableModels());

        if ($model !== '') {
            $input->setOption('model', $model);
        }
    }

    /**
     * Get the console command options.
     */
    protected function getOptions(): array
    {
        return [
            ['model', null, InputOption::VALUE_REQUIRED, 'The model associated with this resource'],
        ];
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Resources';
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param  string  $model
     *
     * @throws InvalidArgumentException
     */
    protected function parseModel($model): string
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }
}
