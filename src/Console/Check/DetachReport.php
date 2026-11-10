<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;

final readonly class DetachReport
{
    /**
     * @param  list<array{resource: string, relation: string}>  $detachable
     * @param  list<string>  $problems
     */
    public function __construct(
        private array $detachable = [],
        private array $problems = [],
    ) {}

    public function withDetachable(string $resource, string $relation): self
    {
        return new self([...$this->detachable, ['resource' => $resource, 'relation' => $relation]], $this->problems);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->detachable, [...$this->problems, $problem]);
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array{relations: array{detachable: list<array{resource: string, relation: string}>, problems: list<string>}} */
    public function toArray(): array
    {
        return ['relations' => ['detachable' => $this->detachable, 'problems' => $this->problems]];
    }

    public function render(Factory $components): void
    {
        if ($this->problems !== []) {
            $components->twoColumnDetail('relations', '<fg=red;options=bold>FAILED</>');
            $components->bulletList($this->problems);

            return;
        }

        $count = count($this->detachable);

        $components->twoColumnDetail('relations', match ($count) {
            0 => 'no relation accepts detach',
            1 => '1 relation accepts detach',
            default => "{$count} relations accept detach",
        });
    }
}
