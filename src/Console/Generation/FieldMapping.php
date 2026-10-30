<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Console\Generation\Schema\Column;
use Lomkit\Rest\Console\Generation\Schema\Table;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;

final readonly class FieldMapping
{
    private function __construct(private ModelColumns $columns, private ImportSet $imports, private ?string $uniqueModel, private string $prefix) {}

    /** @param  list<string>  $relationKeys */
    public static function forModel(ModelTable $source, ImportSet $imports, array $relationKeys): self
    {
        return new self(new ModelColumns($source->model, $relationKeys), $imports, $source->class, '');
    }

    /** @param  list<string>  $timestamps */
    public static function forPivot(Model $pivot, ImportSet $imports, array $timestamps, string $relation): self
    {
        return new self(new ModelColumns($pivot, $timestamps), $imports, null, "{$relation}.pivot.");
    }

    public function map(Table $table): FieldList
    {
        $list = new FieldList;

        foreach ($table->columns as $column) {
            $list = $this->mapColumn($column, $table, $list);
        }

        return $list;
    }

    private function mapColumn(Column $column, Table $table, FieldList $list): FieldList
    {
        if ($this->columns->isLeftOut($column)) {
            return $list->withNote("{$this->prefix}{$column->name}: hidden and not fillable on the model, left out.");
        }

        $class = $this->columns->isKey($column) ? ID::class : $this->columns->typeOf($column)?->value;

        if ($class === null) {
            $list = $list->withNote("{$this->prefix}{$column->name}: no field type matches the column type {$column->typeName}, generated as Text.");
        }

        if ($class === Text::class && $column->isJson()) {
            $list = $list->withNote("{$this->prefix}{$column->name}: {$column->typeName} column without an array cast on the model, generated as Text.");
        }

        return $list->withField($this->declare($column, $class ?? Text::class, $table));
    }

    private function declare(Column $column, string $class, Table $table): Declaration
    {
        $field = Declaration::make($class, var_export($column->name, true));

        if ($column->nullable) {
            $field = $field->with('nullable()');
        }

        if ($this->columns->isManaged($column)) {
            return $class === ID::class ? $field : $field->with('readonly()');
        }

        if ($class === ID::class) {
            $field = $field->with('creatable()');
        }

        if ($this->columns->isHidden($column)) {
            $field = $field->with('writeOnly()');
        }

        $rule = $this->rule($column, $class);

        if ($rule !== null) {
            $field = $field->with("rules('{$rule}')");
        }

        return $this->withCreationRules($field, $column, $table);
    }

    private function rule(Column $column, string $class): ?string
    {
        if ($class === Text::class && $column->isJson()) {
            return 'json';
        }

        $length = $column->length();

        return $length !== null && in_array($class, [ID::class, Text::class], true) ? "max:{$length}" : null;
    }

    private function withCreationRules(Declaration $field, Column $column, Table $table): Declaration
    {
        $rules = $column->isRequired() ? ["'required'"] : [];

        if ($this->uniqueModel === null || ! $table->isUniqueOn($column->name)) {
            return $rules === [] ? $field : $field->with('creationRules('.implode(', ', $rules).')');
        }

        $rules[] = sprintf('%s::unique(%s::class, %s)', $this->imports->name(Rule::class), $this->imports->name($this->uniqueModel), var_export($column->name, true));

        return $field->with('creationRules('.implode(', ', $rules).')', Rule::class);
    }
}
