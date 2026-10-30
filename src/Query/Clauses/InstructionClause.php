<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Laravel\Scout\Builder as ScoutBuilder;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;

final readonly class InstructionClause implements Clause
{
    /** @param list<array{name: string, value?: mixed}> $fields */
    public function __construct(
        private Resource $resource,
        private string $name,
        private array $fields = [],
    ) {}

    #[\Override]
    public function apply(Builder $query): void
    {
        $instruction = $this->resource->instruction($this->resource->context()->request, $this->name)
            ?? throw InvalidInstructionException::undeclared($this->resource::class, $this->name);

        $instruction->handle($this->values($instruction), self::eloquent($query));
    }

    /** @param ScoutBuilder<Model> $query */
    public function applyToScout(ScoutBuilder $query): void
    {
        $instruction = $this->resource->scoutInstruction($this->resource->context()->request, $this->name)
            ?? throw InvalidInstructionException::undeclaredForScout($this->resource::class, $this->name);

        $instruction->handleScout($this->values($instruction), $query);
    }

    /** @return array<string, mixed> */
    private function values(Instruction $instruction): array
    {
        return InputValues::fromNamed($instruction->getFields($this->resource->context()->request), $this->fields)->toArray();
    }

    /** @return EloquentBuilder<Model> */
    private static function eloquent(Builder $query): EloquentBuilder
    {
        return match (true) {
            $query instanceof EloquentBuilder => $query,
            $query instanceof Relation => $query->getQuery(),
            default => throw new InvalidArgumentException(get_debug_type($query).' is not an Eloquent builder.'),
        };
    }
}
