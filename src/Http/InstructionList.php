<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Instructions\Instruction;
use ReflectionClass;

/** @internal */
final class InstructionList
{
    /** @var array<array-key, Instruction> */
    private array $built = [];

    /** @param array<int, Instruction|class-string<Instruction>> $entries */
    public function __construct(
        private readonly array $entries,
        private readonly Resource $resource,
        private readonly Container $container,
    ) {}

    /** @return list<int> */
    public function indexes(): array
    {
        return array_keys($this->entries);
    }

    /** @return array<int, Instruction> */
    public function all(): array
    {
        $instructions = [];

        foreach ($this->indexes() as $index) {
            $instructions[$index] = $this->at($index);
        }

        return $instructions;
    }

    public function at(int|string $index): Instruction
    {
        return $this->built[$index] ??= $this->build($this->entries[$index])->resource($this->resource);
    }

    /** @param Instruction|class-string<Instruction> $entry */
    private function build(Instruction|string $entry): Instruction
    {
        if (! is_string($entry)) {
            return $entry;
        }

        $this->ensureInstantiable($entry);
        $instruction = $this->container->make($entry);

        if (! $instruction instanceof $entry) {
            throw InvalidInstructionException::foreignBinding($this->resource::class, $entry, get_debug_type($instruction));
        }

        return $instruction;
    }

    private function ensureInstantiable(string $entry): void
    {
        if ($entry === Instruction::class) {
            throw InvalidInstructionException::baseListed($this->resource::class);
        }

        if (! is_subclass_of($entry, Instruction::class)) {
            throw InvalidInstructionException::notAnInstruction($this->resource::class, $entry);
        }

        if (! (new ReflectionClass($entry))->isInstantiable()) {
            throw InvalidInstructionException::notInstantiable($this->resource::class, $entry);
        }
    }
}
