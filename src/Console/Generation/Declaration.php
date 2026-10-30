<?php

namespace Lomkit\Rest\Console\Generation;

final readonly class Declaration
{
    /**
     * @param  list<string>  $arguments
     * @param  list<string|ListCall>  $calls
     * @param  list<string>  $uses
     */
    private function __construct(private string $class, private array $arguments, private array $calls, private array $uses) {}

    public static function make(string $class, string ...$arguments): self
    {
        return new self($class, array_values($arguments), [], []);
    }

    public static function indent(string $code): string
    {
        return preg_replace('/^(?=.)/m', '    ', $code) ?? $code;
    }

    public function with(string $call, string ...$uses): self
    {
        return new self($this->class, $this->arguments, [...$this->calls, $call], [...$this->uses, ...array_values($uses)]);
    }

    /** @param  list<Declaration>  $items */
    public function withList(string $method, array $items): self
    {
        return new self($this->class, $this->arguments, [...$this->calls, new ListCall($method, $items)], $this->uses);
    }

    public function using(string ...$classes): self
    {
        return new self($this->class, $this->arguments, $this->calls, [...$this->uses, ...array_values($classes)]);
    }

    /** @return list<string> */
    public function classes(): array
    {
        $classes = [$this->class, ...$this->uses];

        foreach ($this->calls as $call) {
            $classes = $call instanceof ListCall ? [...$classes, ...$call->classes()] : $classes;
        }

        return $classes;
    }

    public function render(ImportSet $imports): string
    {
        $make = $imports->name($this->class).'::make('.implode(', ', $this->arguments).')';
        $calls = array_map(fn (string|ListCall $call): string => '->'.(is_string($call) ? $call : $call->render($imports)), $this->calls);

        if (count($calls) < 2) {
            return $make.implode('', $calls).',';
        }

        return $make."\n".self::indent(implode("\n", $calls)).',';
    }
}
