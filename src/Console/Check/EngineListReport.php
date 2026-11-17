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
     * @param  list<string>  $notes
     * @param  list<string>  $databaseLists
     */
    public function __construct(
        private array $resources = [],
        private array $problems = [],
        private array $notes = [],
        private array $databaseLists = [],
    ) {}

    public function withResource(string $resource): self
    {
        return new self([...$this->resources, $resource], $this->problems, $this->notes, $this->databaseLists);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->resources, [...$this->problems, $problem], $this->notes, $this->databaseLists);
    }

    public function withNote(string $note): self
    {
        return new self($this->resources, $this->problems, [...$this->notes, $note], $this->databaseLists);
    }

    public function withDatabaseLists(string $resource): self
    {
        return new self($this->resources, $this->problems, $this->notes, [...$this->databaseLists, $resource]);
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array{engine_lists: array{resources: list<string>, problems: list<string>, notes: list<string>, database_lists: list<string>}} */
    public function toArray(): array
    {
        return ['engine_lists' => ['resources' => $this->resources, 'problems' => $this->problems, 'notes' => $this->notes, 'database_lists' => $this->databaseLists]];
    }

    public function render(Factory $components): void
    {
        if ($this->resources === [] && $this->problems === [] && $this->notes === []) {
            return;
        }

        if ($this->problems !== []) {
            $components->twoColumnDetail('engine lists', '<fg=red;options=bold>FAILED</>');
            $components->bulletList($this->problems);
            $this->renderNotes($components);

            return;
        }

        $count = count($this->resources);
        $components->twoColumnDetail('engine lists', "{$count} ".Str::plural('resource', $count).($count === 1 ? ' lists' : ' list').' through the search engine');
        $this->renderNotes($components);

        foreach ($this->resources as $resource) {
            $components->twoColumnDetail($resource, in_array($resource, $this->databaseLists, true) ? 'lists through the search engine, or in SQL on request' : 'lists through the search engine');
        }
    }

    private function renderNotes(Factory $components): void
    {
        if ($this->notes !== []) {
            $components->bulletList($this->notes);
        }
    }
}
