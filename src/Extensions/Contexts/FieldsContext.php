<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Fields\FieldReduction;
use Lomkit\Rest\Http\FieldRequests;
use Lomkit\Rest\Http\Resource;

final readonly class FieldsContext
{
    /** @internal */
    public function __construct(
        private string $extension,
        private string $class,
        public Resource $resource,
        public ?Authenticatable $user,
        private FieldRequests $requests,
    ) {}

    public function declares(string $field): bool
    {
        return $this->requests->declares($field);
    }

    public function hide(string ...$fields): void
    {
        $this->reduce(FieldReduction::Hide, $fields);
    }

    public function readOnly(string ...$fields): void
    {
        $this->reduce(FieldReduction::ReadOnly, $fields);
    }

    public function unfilterable(string ...$fields): void
    {
        $this->reduce(FieldReduction::Unfilterable, $fields);
    }

    public function unsortable(string ...$fields): void
    {
        $this->reduce(FieldReduction::Unsortable, $fields);
    }

    /** @param array<string> $fields */
    private function reduce(FieldReduction $reduction, array $fields): void
    {
        foreach ($fields as $field) {
            $this->requests->add($this->extension, $this->class, $reduction, $field);
        }
    }
}
