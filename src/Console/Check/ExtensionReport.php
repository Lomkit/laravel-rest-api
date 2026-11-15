<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;
use Lomkit\Rest\Extensions\ExtensionEntry;

/** @internal */
final readonly class ExtensionReport
{
    private const string DISABLED = 'disabled';

    /**
     * @param  list<array{name: string|null, class: string, origin: string, disabled: bool}>  $registered
     * @param  list<string>  $problems
     * @param  list<string>  $notes
     */
    public function __construct(
        private array $registered = [],
        private array $problems = [],
        private array $notes = [],
    ) {}

    public function withEntry(ExtensionEntry $entry): self
    {
        return new self([...$this->registered, [
            'name' => $entry->name,
            'class' => $entry->class,
            'origin' => $entry->origin->value,
            'disabled' => $entry->disabled,
        ]], $this->problems, $this->notes);
    }

    public function withProblem(string $problem): self
    {
        return new self($this->registered, [...$this->problems, $problem], $this->notes);
    }

    public function withNote(string $note): self
    {
        return new self($this->registered, $this->problems, [...$this->notes, $note]);
    }

    public function passed(): bool
    {
        return $this->problems === [];
    }

    /** @return array{extensions: array{registered: list<array{name: string|null, class: string, origin: string, disabled: bool}>, problems: list<string>, notes: list<string>}} */
    public function toArray(): array
    {
        return ['extensions' => ['registered' => $this->registered, 'problems' => $this->problems, 'notes' => $this->notes]];
    }

    public function render(Factory $components): void
    {
        if ($this->problems === []) {
            $components->twoColumnDetail('extensions', $this->summary());
        } else {
            $components->twoColumnDetail('extensions', '<fg=red;options=bold>FAILED</>');
            $components->bulletList($this->problems);
        }

        if ($this->notes !== []) {
            $components->bulletList($this->notes);
        }

        foreach ($this->registered as $entry) {
            $details = array_filter([$entry['name'] === null ? null : $entry['class'], $entry['origin'], $entry['disabled'] ? self::DISABLED : null]);
            $components->twoColumnDetail($entry['name'] ?? $entry['class'], implode(', ', $details));
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
