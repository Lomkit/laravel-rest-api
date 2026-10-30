<?php

namespace Lomkit\Rest\Console\Generation;

final readonly class RelationList
{
    /**
     * @param  list<Declaration>  $relations
     * @param  list<string>  $foreignKeys
     * @param  list<string>  $skipped
     * @param  list<string>  $notes
     */
    public function __construct(
        public ImportSet $imports,
        public array $relations = [],
        public array $foreignKeys = [],
        public array $skipped = [],
        public array $notes = [],
    ) {}

    /** @param  list<string>  $notes */
    public function with(Declaration $relation, ImportSet $imports, ?string $foreignKey, array $notes): self
    {
        return new self(
            $imports,
            [...$this->relations, $relation],
            $foreignKey === null ? $this->foreignKeys : [...$this->foreignKeys, $foreignKey],
            $this->skipped,
            [...$this->notes, ...$notes],
        );
    }

    public function skipping(string $reason): self
    {
        return new self($this->imports, $this->relations, $this->foreignKeys, [...$this->skipped, $reason], $this->notes);
    }
}
