<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionSupport;
use Lomkit\Rest\Search\InstructionNode;
use Lomkit\Rest\Text\TextBackend;

final readonly class InstructionClause implements Clause
{
    private function __construct(
        private Instruction $instruction,
        private InputValues $fields,
    ) {}

    public static function of(InstructionNode $node): self
    {
        return new self($node->instruction, $node->fields);
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        if (! InstructionSupport::of($this->instruction)->runsOn(TextBackend::Query)) {
            throw InvalidInstructionException::engineOnly($this->instruction::class);
        }

        $this->instruction->handle($this->fields->toArray(), self::eloquent($query));
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
