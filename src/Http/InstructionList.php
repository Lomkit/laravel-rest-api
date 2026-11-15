<?php

namespace Lomkit\Rest\Http;

use Closure;
use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Instructions\Instruction;
use ReflectionClass;
use WeakMap;

/** @internal */
final class InstructionList
{
    private const string OWNER_SEPARATOR = '.';

    /** @var array<array-key, Instruction> */
    private array $built = [];

    /** @var array<int, array{Instruction|class-string<Instruction>, array{string, string}|null}>|null */
    private ?array $listed = null;

    private bool $distinct = false;

    /** @var WeakMap<Instruction, string> */
    private WeakMap $owners;

    /**
     * @param  array<int, Instruction|class-string<Instruction>>  $entries
     * @param  Closure(): array<string, array{string, list<Instruction|class-string<Instruction>>}>  $extensions
     */
    public function __construct(
        private readonly array $entries,
        private readonly Closure $extensions,
        private readonly Resource $resource,
        private readonly Container $container,
    ) {
        $this->owners = new WeakMap;
    }

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
        return array_find($this->all(), fn (Instruction $instruction): bool => $this->keyOf($instruction) === $key);
    }

    public function keyOf(Instruction $instruction): string
    {
        $owner = $this->owners[$instruction] ?? null;

        return $owner === null ? $instruction->uriKey() : $owner.self::OWNER_SEPARATOR.$instruction->uriKey();
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
            $owners[$this->keyOf($instruction)][] = $this->listed()[$index][1];
        }

        foreach ($owners as $key => $declaring) {
            if (count($declaring) > 1 && array_any($declaring, static fn (?array $owner): bool => $owner !== null)) {
                throw InvalidQueryExtensionException::instructionTaken((string) $key, $this->resource::class, $declaring);
            }
        }

        $this->distinct = true;
    }

    /** @return array<int, array{Instruction|class-string<Instruction>, array{string, string}|null}> */
    private function listed(): array
    {
        if ($this->listed !== null) {
            return $this->listed;
        }

        $listed = array_map(static fn (Instruction|string $entry): array => [$entry, null], $this->entries);

        foreach (($this->extensions)() as $name => [$class, $entries]) {
            foreach ($entries as $entry) {
                $listed[] = [$entry, [$name, $class]];
            }
        }

        return $this->listed = $listed;
    }

    /**
     * @param  Instruction|class-string<Instruction>  $entry
     * @param  array{string, string}|null  $owner
     */
    private function build(Instruction|string $entry, ?array $owner): Instruction
    {
        $instruction = is_string($entry) ? $this->make($entry, $owner) : $entry;

        if ($owner === null) {
            return $instruction;
        }

        $clone = clone $instruction;
        $this->owners[$clone] = $owner[0];

        return $clone;
    }

    /**
     * @param  class-string<Instruction>  $entry
     * @param  array{string, string}|null  $owner
     */
    private function make(string $entry, ?array $owner): Instruction
    {
        $this->ensureInstantiable($entry, $owner);
        $instruction = $this->container->make($entry);

        if (! $instruction instanceof $entry) {
            throw $owner === null
                ? InvalidInstructionException::foreignBinding($this->resource::class, $entry, get_debug_type($instruction))
                : InvalidQueryExtensionException::instructionForeignBinding($owner[0], $owner[1], $this->resource::class, $entry, get_debug_type($instruction));
        }

        return $instruction;
    }

    /** @param array{string, string}|null $owner */
    private function ensureInstantiable(string $entry, ?array $owner): void
    {
        $resource = $this->resource::class;

        if ($entry === Instruction::class) {
            throw $owner === null ? InvalidInstructionException::baseListed($resource) : InvalidQueryExtensionException::instructionBaseListed($owner[0], $owner[1], $resource);
        }

        if (! is_subclass_of($entry, Instruction::class)) {
            throw $owner === null ? InvalidInstructionException::notAnInstruction($resource, $entry) : InvalidQueryExtensionException::instructionNotAnInstruction($owner[0], $owner[1], $resource, $entry);
        }

        if (! (new ReflectionClass($entry))->isInstantiable()) {
            throw $owner === null ? InvalidInstructionException::notInstantiable($resource, $entry) : InvalidQueryExtensionException::instructionNotInstantiable($owner[0], $owner[1], $resource, $entry);
        }
    }
}
