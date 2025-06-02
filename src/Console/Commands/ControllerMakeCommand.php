<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

#[AsCommand(name: 'rest:controller')]
class ControllerMakeCommand extends GeneratorCommand
{
    /**
     * The console command name.
     *
     * @var string
     */
    protected $name = 'rest:controller';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new controller class';

    /**
     * The type of class being generated.
     *
     * @var string
     */
    protected $type = 'Controller';

    /**
     * Get the stub file for the generator.
     *
     * @return string
     */
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/controller.stub');
    }

    /**
     * Execute the console command.
     *
     * @return bool|null
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function handle(): ?bool
    {
        $this->callSilent('rest:base-controller', [
            'name' => 'Controller',
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
     * @param  string  $name
     * @return string
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    protected function buildClass($name): string
    {
        $rootNamespace = $this->rootNamespace();

        $replace = [];

        if ($this->option('resource')) {
            $replace = $this->buildResourceReplacements($replace);
        } else {
            $replace = array_merge(
                $replace,
                [
                    '{{ namespacedResource }}' => 'Resource'
                ]
            );
        }

        $baseControllerExists = file_exists($this->getPath("{$rootNamespace}Rest\Controllers\Controller"));

        if (!$baseControllerExists) {
            $replace["use {$rootNamespace}Rest\Controllers\Controller;\n"] = "use Lomkit\\Rest\\Http\\Controllers\\Controller;\n";
        }

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name)
        );
    }

    /**
     * Build the resource replacement values.
     *
     * @param  array  $replace
     * @return array
     */
    protected function buildResourceReplacements(array $replace): array
    {
        $resourceClass = $this->parseResource($this->option('resource'));

        $replace = [];

        return array_merge($replace, [
            'DummyFullResourceClass' => $resourceClass,
            '{{ namespacedResource }}' => $resourceClass,
            '{{namespacedResource}}' => $resourceClass,
            'DummyResourceClass' => class_basename($resourceClass),
            '{{ resource }}' => class_basename($resourceClass),
            '{{resource}}' => class_basename($resourceClass),
            'DummyResourceVariable' => lcfirst(class_basename($resourceClass)),
            '{{ resourceVariable }}' => lcfirst(class_basename($resourceClass)),
            '{{resourceVariable}}' => lcfirst(class_basename($resourceClass)),
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
        $resource = select(
            'What resource should this resource relies to? (Optional)',
            $this->possibleResources(),
            required: false
        );

        if ($resource) {
            $input->setOption('resource', $resource);
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
            ['resource', null, InputOption::VALUE_NONE, 'The resource associated with this resource'],
        ];
    }

    /**
     * Get a list of possible model names.
     *
     * @return array<int, string>
     */
    protected function possibleResources(): array
    {
        $resourcePath = app_path('Rest/Resources');

        return (new Collection(Finder::create()->files()->depth(0)->in($resourcePath)))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();
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
        return $rootNamespace.'\Rest\Controllers';
    }

    /**
     * Get the fully-qualified resource class name.
     *
     * @param  string  $model
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    protected function parseResource($model): string
    {
        if (preg_match('([^A-Za-z0-9_/\\\\])', $model)) {
            throw new InvalidArgumentException('Resource name contains invalid characters.');
        }

        return $this->qualifyResource($model);
    }

    /**
     * Qualify the given resource class base name.
     *
     * @param  string  $resource
     * @return string
     */
    protected function qualifyResource(string $resource): string
    {
        $resource = ltrim($resource, '\\/');

        $resource = str_replace('/', '\\', $resource);

        $rootNamespace = $this->rootNamespace();

        if (Str::startsWith($resource, $rootNamespace)) {
            return $resource;
        }

        return $rootNamespace.'Rest\\Resources\\'.$resource;
    }
}
