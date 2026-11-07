<?php

namespace Lomkit\Rest\Query\Clauses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;
use Lomkit\Rest\Instructions\InstructionSupport;
use Lomkit\Rest\Query\WhereGroup;
use Lomkit\Rest\Search\InstructionNode;
use Lomkit\Rest\Text\TextBackend;

final readonly class InstructionClause implements Clause
{
    private function __construct(
        private Instruction $instruction,
        private InstructionFields $fields,
    ) {}

    public static function of(InstructionNode $node): self
    {
        return new self($node->instruction, InstructionFields::of($node->instruction, $node->fields));
    }

    #[\Override]
    public function apply(Builder $query): void
    {
        if (! InstructionSupport::of($this->instruction)->runsOn(TextBackend::Query)) {
            throw InvalidInstructionException::engineOnly($this->instruction::class);
        }

        WhereGroup::wrap($query, function (EloquentBuilder $query): void {
            $this->instruction->handle($this->fields, $query);
        });
    }
}
