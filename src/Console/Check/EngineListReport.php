<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;

/** @internal */
final readonly class EngineListReport
{
    /**
     * @param  list<string>  $resources
     * @param  list<string>  $problems
     */
    public function __construct(
        private array $resources = [],
        private array $problems = [],
    ) {}

    public function withResource(string $resource): self
    {
        return new self([...$this->resources, $resource], $this->problems);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->resources, [...$this->problems, $problem]);
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array{engine_lists: array{resources: list<string>, problems: list<string>}} */
    public function toArray(): array
    {
        return ['engine_lists' => ['resources' => $this->resources, 'problems' => $this->problems]];
    }

    public function render(Factory $components): void
    {
        if ($this->resources === [] && $this->problems === []) {
            return;
        }

        if ($this->problems !== []) {
            $components->twoColumnDetail('engine lists', '<fg=red;options=bold>FAILED</>');
            $components->bulletList($this->problems);

            return;
        }

        $count = count($this->resources);
        $components->twoColumnDetail('engine lists', "{$count} ".Str::plural('resource', $count).' list through the search engine');

        foreach ($this->resources as $resource) {
            $components->twoColumnDetail($resource, 'lists through the search engine');
        }
    }
}
