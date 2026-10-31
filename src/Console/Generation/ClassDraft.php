<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;

final readonly class ClassDraft
{
    private const string IMPORT_BLOCK = '/(?P<imports>(?:^use [^;{]+;$\n?)+)/m';

    /** @param  array<string, string|null>  $replacements */
    public function __construct(
        public string $class,
        private Stub $stub,
        private string $rootNamespace,
        private array $replacements = [],
        private ?BaseClass $base = null,
    ) {}

    public function render(): string
    {
        return $this->sortImports($this->stub->fill([
            '{{ namespace }}' => Str::beforeLast($this->class, '\\'),
            '{{ rootNamespace }}' => $this->rootNamespace,
            '{{ class }}' => class_basename($this->class),
            ...$this->baseClassImport(),
            ...$this->replacements,
        ]));
    }

    /** @return array<string, null> */
    private function baseClassImport(): array
    {
        if ($this->base === null || ! $this->base->sharesNamespaceWith($this->class)) {
            return [];
        }

        return ["use {$this->base->fqcn};" => null];
    }

    private function sortImports(string $contents): string
    {
        if (preg_match(self::IMPORT_BLOCK, $contents, $match) !== 1) {
            return $contents;
        }

        $imports = explode("\n", trim($match['imports']));
        sort($imports);

        return str_replace(trim($match['imports']), implode("\n", $imports), $contents);
    }
}
