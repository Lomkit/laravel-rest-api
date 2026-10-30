<?php

namespace Lomkit\Rest\Console\Generation\Schema;

final readonly class Column
{
    private const string LENGTH = '/^(?:var)?char(?:acter(?: varying)?)?\((\d+)\)$/';

    public function __construct(
        public string $name,
        public string $typeName,
        public string $type,
        public bool $nullable,
        public bool $hasDefault,
        public bool $autoIncrement,
        public bool $generated,
    ) {}

    /** @param array{name: string, type: string, type_name: string, collation: string|null, nullable: bool, default: mixed, auto_increment: bool, comment: string|null, generation: array{type: string, expression: string|null}|null} $column */
    public static function fromSchema(array $column): self
    {
        return new self(
            $column['name'],
            strtolower($column['type_name']),
            strtolower($column['type']),
            $column['nullable'],
            $column['default'] !== null,
            $column['auto_increment'],
            $column['generation'] !== null,
        );
    }

    public function isRequired(): bool
    {
        return ! $this->nullable && ! $this->hasDefault;
    }

    public function isJson(): bool
    {
        return in_array($this->typeName, ['json', 'jsonb'], true);
    }

    public function length(): ?int
    {
        if (preg_match(self::LENGTH, $this->type, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1];
    }
}
