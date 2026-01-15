<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

#[AsCommand(name: 'rest:resource')]
class ResourceMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'rest:resource';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new resource class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Resource';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/resource.stub');
    }

    /**
     * Execute the console command.
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @return bool|null
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
     * @param string $stub
     *
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
     * @param string $name
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     *
     * @return string
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

        if (!$baseResourceExists) {
            $replace["use {$rootNamespace}Rest\Resources\Resource;\n"] = "use Lomkit\\Rest\\Http\\Resource;\n";
        }

        return str_replace(
            array_keys($replace),
            array_values($replace),
            parent::buildClass($name)
        );
    }

    /**
     * Build the model replacement values.
     *
     * @param array $replace
     *
     * @return array
     */
    protected function buildModelReplacements(array $replace): array
    {
        $modelClass = $this->parseModel($this->option('model'));

        return array_merge($replace, [
            'DummyFullModelClass'   => $modelClass,
            '{{ namespacedModel }}' => $modelClass,
            '{{namespacedModel}}'   => $modelClass,
            'DummyModelClass'       => class_basename($modelClass),
            '{{ model }}'           => class_basename($modelClass),
            '{{model}}'             => class_basename($modelClass),
            'DummyModelVariable'    => lcfirst(class_basename($modelClass)),
            '{{ modelVariable }}'   => lcfirst(class_basename($modelClass)),
            '{{modelVariable}}'     => lcfirst(class_basename($modelClass)),
        ]);
    }

    /**
     * Interact further with the user if they were prompted for missing arguments.
     *
     * @param \Symfony\Component\Console\Input\InputInterface   $input
     * @param \Symfony\Component\Console\Output\OutputInterface $output
     *
     * @return void
     */
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        $model = search(
            'What model should this resource relies to? (Optional)',
            fn ($value) => strlen($value) > 0
                ? array_filter($this->possibleModels(), fn ($model) => stripos($model, $value) !== false)
                : $this->possibleModels()
        );

        if ($model) {
            $input->setOption('model', $model);
        }
    }

    /**
     * Get the console command options.
     *
     * @return array
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
     * @param string $rootNamespace
     *
     * @return string
     */
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Resources';
    }

    /**
     * Get the fully-qualified model class name.
     *
     * @param string $model
     *
     * @throws \InvalidArgumentException
     *
     * @return string
     */
    protected function parseModel($model): string
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Model name contains invalid characters.');
        }

        return $this->qualifyModel($model);
    }
}
