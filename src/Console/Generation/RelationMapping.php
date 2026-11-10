<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo as EloquentBelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany as EloquentBelongsToMany;
use Illuminate\Database\Eloquent\Relations\MorphTo as EloquentMorphTo;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\Generation\Schema\Table;
use Lomkit\Rest\Console\Generation\Schema\TableReader;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Mutations\LinkColumn;
use Lomkit\Rest\Mutations\NullableColumns;

final readonly class RelationMapping
{
    /** @param  array<string, string>  $resources */
    private function __construct(private array $resources, private string $modelNamespace, private MissingResourceHint $hint, private NullableColumns $columns) {}

    public static function using(ResourceIndex $resources, string $modelNamespace, MissingResourceHint $hint = MissingResourceHint::GenerateTheResource): self
    {
        return new self($resources->byModel(), $modelNamespace, $hint, new NullableColumns);
    }

    public function map(ModelTable $source, Table $table, ImportSet $imports): RelationList
    {
        $list = new RelationList($imports);

        foreach (ModelRelations::of($source->model) as $name => $relation) {
            $list = $this->mapRelation($name, $relation, $table, $list);
        }

        return $list;
    }

    /** @param  Relation<Model, Model, mixed>  $relation */
    private function mapRelation(string $name, Relation $relation, Table $table, RelationList $list): RelationList
    {
        if ($relation instanceof EloquentMorphTo) {
            return $list->skipping($this->morphToNote($name, new MorphToTargets($relation)));
        }

        $type = RelationType::of($relation);

        if ($type === null) {
            return $list->skipping("{$name}: ".class_basename($relation).' relations are not generated, declare it in relations() by hand.');
        }

        $related = $relation->getRelated()::class;
        $resource = $this->resources[$related] ?? null;

        if ($resource === null) {
            return $list->skipping($this->missingResource($name, $related));
        }

        $imports = $list->imports->with($resource, 'Resource');
        $declaration = Declaration::make($type->value, var_export($name, true), $imports->name($resource).'::class')->using($resource);

        if ($relation instanceof EloquentBelongsTo) {
            $key = $relation->getForeignKeyName();
            $required = $table->column($key)?->isRequired() === true;
            $declaration = $required ? $declaration->with('requiredOnCreation()') : $declaration;

            return $list->with($this->keepingLinks($relation, $declaration, $imports), $imports, $key, []);
        }

        if (! $relation instanceof EloquentBelongsToMany) {
            return $list->with($this->keepingLinks($relation, $declaration, $imports), $imports, null, []);
        }

        $pivot = $this->pivotFields($name, $relation, $imports);

        return $list->with($pivot->fields === [] ? $declaration : $declaration->withList('pivotFields', $pivot->fields), $imports, null, $pivot->notes);
    }

    /** @param  Relation<*, *, *>  $relation */
    private function keepingLinks(Relation $relation, Declaration $declaration, ImportSet $imports): Declaration
    {
        if (LinkColumn::detachedBy($relation)?->nullable($this->columns) !== false) {
            return $declaration;
        }

        return $declaration->with('withoutOperations('.$imports->name(MutationOperation::class).'::Detach)', MutationOperation::class);
    }

    /** @param  EloquentBelongsToMany<Model, Model, *, *>  $relation */
    private function pivotFields(string $name, EloquentBelongsToMany $relation, ImportSet $imports): FieldList
    {
        $columns = array_values(array_unique(array_filter($relation->getPivotColumns(), is_string(...))));

        if ($columns === []) {
            return new FieldList;
        }

        $pivot = new ($relation->getPivotClass());
        $pivot->timestamps = false;
        $timestamps = $relation->withTimestamps ? [$relation->createdAt(), $relation->updatedAt()] : [];
        $schema = $relation->getRelated()
            ->getConnection()
            ->getSchemaBuilder();
        $table = (new TableReader($schema))->read($relation->getTable());
        $fields = FieldMapping::forPivot($pivot, $imports, $timestamps, $name)->map($table->only($columns));

        foreach ($columns as $column) {
            if ($table->column($column) === null) {
                $fields = $fields->withNote("{$name}.pivot.{$column}: no such column in the {$table->name} pivot table, left out.");
            }
        }

        return $fields;
    }

    private function morphToNote(string $name, MorphToTargets $targets): string
    {
        $note = "{$name}: MorphTo relations are not generated, declare it in relations() by hand with its targets";
        $resources = $targets->resources($this->resources);

        if ($resources === []) {
            return "{$note}.";
        }

        $classes = implode(', ', array_map(fn (string $resource): string => class_basename($resource).'::class', $resources));

        return "{$note}, for example MorphTo::make('{$name}', [{$classes}]).";
    }

    private function missingResource(string $name, string $model): string
    {
        $option = str_starts_with($model, $this->modelNamespace) ? Str::after($model, $this->modelNamespace) : $model;

        return "{$name}: no resource for {$model}. ".$this->hint->for($model, str_replace('\\', '/', $option));
    }
}
