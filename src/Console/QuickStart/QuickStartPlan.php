<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Contracts\Auth\Access\Gate;
use Lomkit\Rest\Console\Generation\ClassDraft;
use Lomkit\Rest\Console\Generation\MissingResourceHint;
use Lomkit\Rest\Console\Generation\ModelTable;
use Lomkit\Rest\Console\Generation\RelationMapping;
use Lomkit\Rest\Console\Generation\Stub;

final readonly class QuickStartPlan
{
    /**
     * @param  list<ExposedModel>  $models
     * @param  list<PlannedFile>  $classes
     * @param  list<PlannedFile>  $policies
     */
    private function __construct(public array $models, private array $classes, public array $policies) {}

    /** @param  list<ModelTable>  $sources */
    public static function for(array $sources, AppNamespace $app, Gate $gate, string $user): self
    {
        $models = array_map(fn (ModelTable $source): ExposedModel => ExposedModel::of($source, $app), $sources);
        $index = $app->resources();

        foreach ($models as $model) {
            $index = $index->withPlanned($model->resource, $model->source->class);
        }

        $relations = RelationMapping::using($index, $app->models(), MissingResourceHint::ExposeTheModel);

        return new self(
            $models,
            [
                PlannedFile::draft(new ClassDraft($app->baseResource()->fqcn, Stub::BaseResource, $app->root), $app),
                ...array_map(fn (ExposedModel $model): PlannedFile => $model->resourceFile($app, $relations), $models),
                PlannedFile::draft(new ClassDraft($app->baseController()->fqcn, Stub::BaseController, $app->root), $app),
                ...array_map(fn (ExposedModel $model): PlannedFile => $model->controllerFile($app), $models),
            ],
            array_map(fn (ExposedModel $model): PlannedFile => $model->policyFile($app, $gate, $user), $models),
        );
    }

    /** @return list<PlannedFile> */
    public function files(): array
    {
        return [...$this->classes, ...$this->policies];
    }

    /** @return list<string> */
    public function controllers(): array
    {
        return array_map(fn (ExposedModel $model): string => $model->controller, $this->models);
    }
}
