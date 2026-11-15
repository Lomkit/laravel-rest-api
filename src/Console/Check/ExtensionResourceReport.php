<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;

final readonly class ExtensionResourceReport
{
    /**
     * @param  list<array{extension: string, resource: string}>  $checked
     * @param  list<string>  $problems
     */
    public function __construct(
        private array $checked = [],
        private array $problems = [],
    ) {}

    public function withCheck(string $extension, string $resource): self
    {
        return new self([...$this->checked, ['extension' => $extension, 'resource' => $resource]], $this->problems);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->checked, [...$this->problems, $problem]);
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array{extension_checks: array{checked: list<array{extension: string, resource: string}>, problems: list<string>}} */
    public function toArray(): array
    {
        return ['extension_checks' => ['checked' => $this->checked, 'problems' => $this->problems]];
    }

    public function render(Factory $components): void
    {
        if ($this->checked === [] && $this->problems === []) {
            return;
        }

        if ($this->problems !== []) {
            $components->twoColumnDetail('extension checks', '<fg=red;options=bold>FAILED</>');
            $components->bulletList($this->problems);

            return;
        }

        $count = count($this->checked);
        $components->twoColumnDetail('extension checks', "{$count} ".Str::plural('check', $count).' passed');
    }
}
