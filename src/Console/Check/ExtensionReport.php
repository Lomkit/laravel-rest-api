<?php

namespace Lomkit\Rest\Console\Check;

use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Str;
use Lomkit\Rest\Extensions\ExtensionEntry;
use Lomkit\Rest\Extensions\ExtensionOrigin;

final readonly class ExtensionReport
{
    private const string DISABLED = 'disabled';

    private const string REPLACES = 'replaces';

    /**
     * @param  list<array{name: string, class: string|null, origin: string, disabled: bool, runtime: list<string>}>  $registered
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
            'runtime' => $entry->runtime,
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

    /** @return array{extensions: array{registered: list<array{name: string, class: string|null, origin: string, disabled: bool, runtime: list<string>}>, problems: list<string>}} */
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
            $details = array_filter([$entry['class'] ?? implode(', ', $entry['runtime']), $entry['origin'], $entry['disabled'] ? self::DISABLED : null, self::replaced($entry)]);
            $components->twoColumnDetail($entry['name'], implode(', ', $details));
        }
    }

    /** @param array{name: string, class: string|null, origin: string, disabled: bool, runtime: list<string>} $entry */
    private static function replaced(array $entry): ?string
    {
        $others = array_diff($entry['runtime'], [$entry['class']]);

        return $entry['origin'] === ExtensionOrigin::Config->value && $others !== [] ? self::REPLACES.' '.implode(', ', $others) : null;
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
