<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Instructions\Instruction;
use ReflectionClass;

/** @internal */
final class InstructionList
{
    /** @var array<array-key, Instruction> */
    private array $built = [];

    /** @var array<int, array{Instruction|class-string<Instruction>, ?string}>|null */
    private ?array $listed = null;

    private bool $distinct = false;

    /**
     * @param  array<int, Instruction|class-string<Instruction>>  $entries
     * @param  Closure(): array<string, list<Instruction|class-string<Instruction>>>  $extensions
     */
    public function __construct(
        private readonly array $entries,
        private readonly Closure $extensions,
        private readonly Resource $resource,
        private readonly Container $container,
    ) {}

    /** @return list<int> */
    public function indexes(): array
    {
        return array_keys($this->listed());
    }

    /** @return array<int, Instruction> */
    public function all(): array
    {
        $instructions = [];

        foreach ($this->indexes() as $index) {
            $instructions[$index] = $this->at($index);
        }

        $this->ensureDistinct($instructions);

        return $instructions;
    }

    public function find(string $key): ?Instruction
    {
        return array_find($this->all(), fn (Instruction $instruction): bool => $instruction->publicUriKey() === $key);
    }

    public function at(int|string $index): Instruction
    {
        return $this->built[$index] ??= $this->build(...$this->listed()[$index])->resource($this->resource);
    }

    /** @param array<int, Instruction> $instructions */
    private function ensureDistinct(array $instructions): void
    {
        if ($this->distinct) {
            return;
        }

        $owners = [];

        foreach ($instructions as $index => $instruction) {
            $owners[$instruction->publicUriKey()][] = $this->listed()[$index][1];
        }

        foreach ($owners as $key => $declaring) {
            if (count($declaring) > 1 && array_any($declaring, static fn (?string $owner): bool => $owner !== null)) {
                throw InvalidQueryExtensionException::instructionTaken((string) $key, $this->resource::class, $declaring);
            }
        }

        $this->distinct = true;
    }

    /** @return array<int, array{Instruction|class-string<Instruction>, ?string}> */
    private function listed(): array
    {
        if ($this->listed !== null) {
            return $this->listed;
        }

        $listed = array_map(static fn (Instruction|string $entry): array => [$entry, null], $this->entries);

        foreach (($this->extensions)() as $name => $entries) {
            foreach ($entries as $entry) {
                $listed[] = [$entry, $name];
            }
        }

        return $this->listed = $listed;
    }

    /** @param Instruction|class-string<Instruction> $entry */
    private function build(Instruction|string $entry, ?string $owner): Instruction
    {
        if (! is_string($entry)) {
            return $owner === null ? $entry : (clone $entry)->declaredBy($owner);
        }

        $this->ensureInstantiable($entry, $owner);
        $instruction = $this->container->make($entry);

        if (! $instruction instanceof $entry) {
            throw $owner === null
                ? InvalidInstructionException::foreignBinding($this->resource::class, $entry, get_debug_type($instruction))
                : InvalidQueryExtensionException::instructionForeignBinding($owner, $this->resource::class, $entry, get_debug_type($instruction));
        }

        return $owner === null ? $instruction : $instruction->declaredBy($owner);
    }

    private function ensureInstantiable(string $entry, ?string $owner): void
    {
        $resource = $this->resource::class;

        if ($entry === Instruction::class) {
            throw $owner === null ? InvalidInstructionException::baseListed($resource) : InvalidQueryExtensionException::instructionBaseListed($owner, $resource);
        }

        if (! is_subclass_of($entry, Instruction::class)) {
            throw $owner === null ? InvalidInstructionException::notAnInstruction($resource, $entry) : InvalidQueryExtensionException::instructionNotAnInstruction($owner, $resource, $entry);
        }

        if (! (new ReflectionClass($entry))->isInstantiable()) {
            throw $owner === null ? InvalidInstructionException::notInstantiable($resource, $entry) : InvalidQueryExtensionException::instructionNotInstantiable($owner, $resource, $entry);
        }
    }
}
