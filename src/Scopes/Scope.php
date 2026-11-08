<?php

namespace Lomkit\Rest\Scopes;

use Lomkit\Rest\Concerns\HasMeta;
use Lomkit\Rest\Exceptions\InvalidScopeException;
use Lomkit\Rest\Fields\Details\InputFieldDetails;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Details\DetailsSelection;

final class Scope
{
    use HasMeta;

    private FieldSet $fields;

    private function __construct(public readonly string $name)
    {
        $this->fields = FieldSet::of([]);
    }

    public static function make(string $name): self
    {
        return new self($name);
    }

    /**
     * @param  array<mixed>  $scopes
     * @return list<self>
     */
    public static function listOf(array $scopes, string $owner): array
    {
        $declared = [];

        foreach (array_values($scopes) as $index => $scope) {
            if (! $scope instanceof self) {
                throw InvalidScopeException::notAScope($owner, $index, $scope);
            }

            $declared[] = $scope;
        }

        return $declared;
    }

    /** @param list<Field> $fields */
    public function fields(array $fields): self
    {
        $this->fields = FieldSet::forInputs($fields, "Scope::make('{$this->name}')->fields()");

        return $this;
    }

    public function getFields(): FieldSet
    {
        return $this->fields;
    }

    /** @return array<string, mixed> */
    public function toDetails(DetailsSelection $selection): array
    {
        return [
            'name' => $this->name,
            ...$selection->part('scopes.fields', fn (): array => ['fields' => InputFieldDetails::forFields($this->fields)]),
            ...$selection->part('scopes.meta', fn (): array => ['meta' => (object) $this->getMeta()]),
        ];
    }
}
