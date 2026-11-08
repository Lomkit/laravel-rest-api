<?php

namespace Lomkit\Rest\Instructions;

use Lomkit\Rest\Support\Overrides;
use Lomkit\Rest\Text\TextBackend;

final readonly class InstructionSupport
{
    private function __construct(
        private bool $query,
        private bool $engine,
    ) {}

    public static function of(Instruction $instruction): self
    {
        $engine = $instruction instanceof AppliesToEngine;
        $handled = Overrides::method($instruction, 'handle', Instruction::class);

        return new self(! $engine || $handled, $engine);
    }

    /**
     * @param  array<int, Instruction>  $instructions
     * @return array<int, Instruction>
     */
    public static function among(array $instructions, TextBackend $backend): array
    {
        return array_filter($instructions, fn (Instruction $instruction): bool => self::of($instruction)->runsOn($backend));
    }

    public function runsOn(TextBackend $backend): bool
    {
        return $backend === TextBackend::Engine ? $this->engine : $this->query;
    }

    public function runsWithText(TextBackend $backend): bool
    {
        return $backend !== TextBackend::None && $this->runsOn($backend);
    }
}
