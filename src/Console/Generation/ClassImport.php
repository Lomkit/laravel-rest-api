<?php

namespace Lomkit\Rest\Console\Generation;

final readonly class ClassImport
{
    private function __construct(private string $fqcn, private string $name) {}

    /** @param  list<string>  $takenNames */
    public static function of(string $fqcn, array $takenNames, string $aliasSuffix): self
    {
        $taken = array_map(strtolower(...), $takenNames);
        $name = class_basename($fqcn);

        while (in_array(strtolower($name), $taken, true)) {
            $name .= $aliasSuffix;
        }

        return new self($fqcn, $name);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function statement(): string
    {
        return $this->name === class_basename($this->fqcn) ? $this->fqcn : "{$this->fqcn} as {$this->name}";
    }
}
