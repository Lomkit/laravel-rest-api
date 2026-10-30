<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;
use Lomkit\Rest\Fields\ID;

final readonly class ResourceBlueprint
{
    /**
     * @param  list<Declaration>  $fields
     * @param  list<Declaration>  $relations
     * @param  list<string>  $skipped
     * @param  list<string>  $notes
     */
    private function __construct(
        private ImportSet $imports,
        private ?string $model,
        private array $fields,
        private array $relations = [],
        private array $skipped = [],
        private array $notes = [],
    ) {}

    public static function withKeyOnly(ImportSet $imports, ?string $model, string $keyName): self
    {
        return new self(
            $model === null ? $imports : $imports->with($model, 'Model'),
            $model,
            [Declaration::make(ID::class, var_export($keyName, true))],
        );
    }

    public static function fromTable(ImportSet $imports, ModelTable $source, RelationMapping $mapping): self
    {
        $table = $source->read();
        $relations = $mapping->map($source, $table, $imports->with($source->class, 'Model'));
        $fields = FieldMapping::forModel($source, $relations->imports, $relations->foreignKeys)->map($table);

        return new self(
            $relations->imports,
            $source->class,
            $fields->fields,
            $relations->relations,
            $relations->skipped,
            [...$fields->notes, ...$relations->notes],
        );
    }

    /** @return array<string, string|null> */
    public function replacements(): array
    {
        return [
            '{{ namespacedModel }}' => $this->model === null ? null : $this->imports->statement($this->model),
            '{{ model }}' => $this->model === null ? null : $this->imports->name($this->model),
            '{{ imports }}' => $this->lines($this->uses()),
            '{{ fields }}' => $this->lines($this->rendered($this->fields)),
            '{{ relations }}' => $this->lines($this->rendered($this->relations)),
        ];
    }

    public function summary(): string
    {
        $fields = count($this->fields);
        $relations = count($this->relations);

        return "{$fields} ".Str::plural('field', $fields).", {$relations} ".Str::plural('relation', $relations);
    }

    /** @return list<string> */
    public function skipped(): array
    {
        return $this->skipped;
    }

    /** @return list<string> */
    public function notes(): array
    {
        return $this->notes;
    }

    /** @return list<string> */
    private function uses(): array
    {
        $uses = [];

        foreach ([...$this->fields, ...$this->relations] as $declaration) {
            foreach ($declaration->classes() as $class) {
                $statement = $this->imports->statement($class);
                $uses[$class] = $statement === null ? null : "use {$statement};";
            }
        }

        $uses = array_values(array_filter($uses, is_string(...)));
        sort($uses);

        return $uses;
    }

    /**
     * @param  list<Declaration>  $declarations
     * @return list<string>
     */
    private function rendered(array $declarations): array
    {
        return array_map(fn (Declaration $declaration): string => $declaration->render($this->imports), $declarations);
    }

    /** @param  list<string>  $lines */
    private function lines(array $lines): ?string
    {
        return $lines === [] ? null : implode("\n", $lines);
    }
}
