<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\Generation\ClassDraft;
use Lomkit\Rest\Console\Generation\ImportSet;
use Lomkit\Rest\Console\Generation\ModelTable;
use Lomkit\Rest\Console\Generation\PolicyBlueprint;
use Lomkit\Rest\Console\Generation\RelationMapping;
use Lomkit\Rest\Console\Generation\ResourceBlueprint;
use Lomkit\Rest\Console\Generation\Stub;

final readonly class ExposedModel
{
    private function __construct(
        public ModelTable $source,
        public string $resource,
        public string $controller,
        public string $policy,
    ) {}

    public static function of(ModelTable $source, AppNamespace $app): self
    {
        $relative = str_starts_with($source->class, $app->models()) ? Str::after($source->class, $app->models()) : class_basename($source->class);
        $directory = str_contains($relative, '\\') ? Str::beforeLast($relative, '\\').'\\' : '';

        return new self(
            $source,
            "{$app->root}Rest\\Resources\\{$relative}Resource",
            "{$app->root}Rest\\Controllers\\{$directory}".Str::pluralStudly(class_basename($source->class)).'Controller',
            "{$app->root}Policies\\{$relative}Policy",
        );
    }

    public function name(): string
    {
        return class_basename($this->source->class);
    }

    public function uri(): string
    {
        return Str::kebab(Str::pluralStudly($this->name()));
    }

    public function resourceFile(AppNamespace $app, RelationMapping $relations): PlannedFile
    {
        $base = $app->baseResource();
        $blueprint = ResourceBlueprint::fromTable(ImportSet::forResource($this->resource, $base->fqcn), $this->source, $relations);
        $remarks = [
            ...array_map(fn (string $skipped): string => "Skipped: {$this->name()}.{$skipped}", $blueprint->skipped()),
            ...array_map(fn (string $note): string => "Note: {$this->name()}.{$note}", $blueprint->notes()),
        ];
        $draft = new ClassDraft($this->resource, Stub::Resource, $app->root, $blueprint->replacements(), $base);

        return PlannedFile::draft($draft, $app, "({$blueprint->summary()})", $remarks);
    }

    public function controllerFile(AppNamespace $app): PlannedFile
    {
        $base = $app->baseController();
        $imports = ImportSet::forController($this->controller, $base->fqcn)->with($this->resource, 'Resource');
        $replacements = [
            '{{ namespacedResource }}' => $imports->statement($this->resource),
            '{{ resource }}' => $imports->name($this->resource),
        ];

        return PlannedFile::draft(new ClassDraft($this->controller, Stub::Controller, $app->root, $replacements, $base), $app);
    }

    public function policyFile(AppNamespace $app, Gate $gate, string $user): PlannedFile
    {
        $policy = $gate->getPolicyFor($this->source->class);

        if (is_object($policy)) {
            return PlannedFile::existing($policy::class);
        }

        $blueprint = PolicyBlueprint::for($this->policy, $this->source->class, $user);

        return PlannedFile::draft(new ClassDraft($this->policy, Stub::Policy, $app->root, $blueprint->replacements()), $app, '(read-only)');
    }
}
