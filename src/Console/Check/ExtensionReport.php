<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;
use Lomkit\Rest\Extensions\ExtensionEntry;

final readonly class ExtensionReport
{
    private const string DISABLED = 'disabled';

    private const string REPLACES = 'replaces';

    /**
     * @param  list<array{name: string, class: string|null, origin: string, disabled: bool, replaces: string|null}>  $registered
     * @param  list<string>  $problems
     */
    public function __construct(
        private array $registered = [],
        private array $problems = [],
    ) {}

    public function withEntry(ExtensionEntry $entry): self
    {
        return new self([...$this->registered, [
            'name' => $entry->name,
            'class' => $entry->class,
            'origin' => $entry->origin->value,
            'disabled' => $entry->disabled,
            'replaces' => $entry->replaces,
        ]], $this->problems);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->registered, [...$this->problems, $problem]);
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array{extensions: array{registered: list<array{name: string, class: string|null, origin: string, disabled: bool, replaces: string|null}>, problems: list<string>}} */
    public function toArray(): array
    {
        return ['extensions' => ['registered' => $this->registered, 'problems' => $this->problems]];
    }

    public function render(Factory $components): void
    {
        if ($this->problems === []) {
            $components->twoColumnDetail('extensions', $this->summary());
        } else {
            $components->twoColumnDetail('extensions', '<fg=red;options=bold>FAILED</>');
            $components->bulletList($this->problems);
        }

        foreach ($this->registered as $entry) {
            $replaces = $entry['replaces'] === null ? null : self::REPLACES.' '.$entry['replaces'];
            $details = array_filter([$entry['class'], $entry['origin'], $entry['disabled'] ? self::DISABLED : null, $replaces]);
            $components->twoColumnDetail($entry['name'], implode(', ', $details));
        }
    }

    private function summary(): string
    {
        if ($this->registered === []) {
            return 'none registered';
        }

        $disabled = count(array_filter($this->registered, fn (array $entry): bool => $entry['disabled']));
        $built = count($this->registered) - $disabled;
        $summary = "{$built} ".Str::plural('extension', $built).' built';

        return $disabled === 0 ? $summary : "{$summary}, {$disabled} ".self::DISABLED;
    }
}
