<?php

namespace Lomkit\Rest\Console\Generation;

final readonly class ListCall
{
    /** @param  list<Declaration>  $items */
    public function __construct(private string $method, private array $items) {}

    /** @return list<string> */
    public function classes(): array
    {
        return array_merge([], ...array_map(fn (Declaration $item): array => $item->classes(), $this->items));
    }

    public function render(ImportSet $imports): string
    {
        $items = array_map(fn (Declaration $item): string => Declaration::indent($item->render($imports)), $this->items);

        return "{$this->method}([\n".implode("\n", $items)."\n])";
    }
}
