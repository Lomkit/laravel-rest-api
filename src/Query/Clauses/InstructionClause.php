<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionSupport;
use Lomkit\Rest\Text\TextBackend;

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

        if (! InstructionSupport::of($instruction)->runsOn(TextBackend::Query)) {
            throw InvalidInstructionException::engineOnly($instruction::class);
        }

        $instruction->handle($this->values($instruction), self::eloquent($query));
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
