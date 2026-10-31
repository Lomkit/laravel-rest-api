<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Filesystem\Filesystem;
use Lomkit\Rest\Exceptions\InvalidModelTableException;

final readonly class ApiRoutes
{
    public const string PATH = 'routes/api.php';

    public function __construct(private Filesystem $files, private string $basePath) {}

    public function exist(): bool
    {
        return $this->files->exists($this->path());
    }

    /** @return list<ExposedModel> */
    public function unregistered(QuickStartPlan $plan): array
    {
        $routes = $this->file();

        return array_values(array_filter($plan->models, fn (ExposedModel $model): bool => ! $routes->registers($model->controller)));
    }

    public function ensureDistinctNames(QuickStartPlan $plan): void
    {
        $classes = [];

        foreach ($this->unregistered($plan) as $model) {
            $classes[$model->uri()][] = $model->source->class;
        }

        foreach ($classes as $name => $sharing) {
            if (count($sharing) > 1) {
                throw InvalidModelTableException::sharedResourceName($name, $sharing);
            }
        }
    }

    /** @return list<string> */
    public function append(QuickStartPlan $plan): array
    {
        $routes = $this->file();
        $added = [];

        foreach ($this->unregistered($plan) as $model) {
            $routes = $routes->withResource($model->uri(), $model->controller);
            $added[] = $model->uri();
        }

        if ($added !== []) {
            $this->files->put($this->path(), $routes->contents);
        }

        return $added;
    }

    private function file(): RouteFile
    {
        return new RouteFile($this->exist() ? $this->files->get($this->path()) : '<?php');
    }

    private function path(): string
    {
        return $this->basePath.'/'.self::PATH;
    }
}
