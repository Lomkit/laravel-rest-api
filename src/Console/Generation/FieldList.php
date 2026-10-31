<?php

namespace Lomkit\Rest\Console\Generation;

final readonly class FieldList
{
    /**
     * @param  list<Declaration>  $fields
     * @param  list<string>  $notes
     */
    public function __construct(public array $fields = [], public array $notes = []) {}

    public function withField(Declaration $field): self
    {
        return new self([...$this->fields, $field], $this->notes);
    }

    public function withNote(string $note): self
    {
        return new self($this->fields, [...$this->notes, $note]);
    }
}
