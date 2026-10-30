<?php

namespace Lomkit\Rest\Console\Commands;

use Illuminate\Console\GeneratorCommand;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Finder\Finder;

use function Laravel\Prompts\suggest;

#[AsCommand(name: 'rest:controller', description: 'Create a new controller class')]
class ControllerMakeCommand extends GeneratorCommand
{
    protected $type = 'Controller';

    /**
     * Get the stub file for the generator.
     */
    #[\Override]
    protected function getStub(): string
    {
        return $this->resolveStubPath('/../stubs/controller.stub');
    }

    /**
     * Execute the console command.
     *
     * @throws FileNotFoundException
     */
    #[\Override]
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
    #[\Override]
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
                    '{{ namespacedResource }}' => 'Resource',
                ]
            );
        }

        $baseControllerExists = file_exists($this->getPath("{$rootNamespace}Rest\Controllers\Controller"));

        if (! $baseControllerExists) {
            $replace["use {$rootNamespace}Rest\Controllers\Controller;\n"] = "use Lomkit\\Rest\\Http\\Controllers\\Controller;\n";
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
    protected function buildResourceReplacements(array $replace): array
    {
        $resourceClass = $this->parseResource($this->option('resource'));

        return array_merge($replace, [
            '{{ namespacedResource }}' => $resourceClass,
            '{{namespacedResource}}' => $resourceClass,
        ]);
    }

    #[\Override]
    protected function afterPromptingForMissingArguments(InputInterface $input, OutputInterface $output): void
    {
        if ($this->didReceiveOptions($input)) {
            return;
        }

        $resource = suggest('What resource should this controller rely on? (Optional)', $this->possibleResources());

        if ($resource !== '') {
            $input->setOption('resource', $resource);
        }
    }

    /**
     * Get the console command options.
     */
    #[\Override]
    protected function getOptions(): array
    {
        return [
            ['resource', null, InputOption::VALUE_REQUIRED, 'The resource associated with this controller'],
        ];
    }

    /** @return array<int, string> */
    protected function possibleResources(): array
    {
        $resourcePath = app_path('Rest/Resources');

        if (! is_dir($resourcePath)) {
            return [];
        }

        return (new Collection(Finder::create()->files()->depth(0)->in($resourcePath)))
            ->map(fn ($file) => $file->getBasename('.php'))
            ->sort()
            ->values()
            ->all();
    }

    /**
     * Get the default namespace for the class.
     *
     * @param  string  $rootNamespace
     */
    #[\Override]
    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace.'\Rest\Controllers';
    }

    /**
     * Get the fully-qualified resource class name.
     *
     * @param  string  $model
     *
     * @throws InvalidArgumentException
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
