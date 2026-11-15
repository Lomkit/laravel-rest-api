<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidFieldExtensionException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\FieldReduction;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\NarrowedFields;
use Lomkit\Rest\Text\QueryTextSearch;
use Lomkit\Rest\Text\ScoutEngine;
use Lomkit\Rest\Text\TextSearch;

/** @internal */
final class FieldRequests
{
    /** @var array<string, list<string>> */
    private array $names = [];

    private bool $textRead = false;

    private ?TextSearch $text = null;

    public function __construct(
        private readonly Resource $resource,
        private readonly FieldSet $declared,
        private readonly ?string $version,
        private readonly ?string $key,
    ) {}

    public function declares(string $field): bool
    {
        return $this->declared->has($field);
    }

    public function add(string $extension, FieldReduction $reduction, string $field): void
    {
        $declared = $this->declared->find($field)
            ?? throw InvalidFieldExtensionException::unknownField($extension, $this->resource::class, $reduction, $field);

        if ($reduction === FieldReduction::Hide) {
            $this->ensureHideable($extension, $declared);
        }

        $listed = $this->names[$reduction->value] ?? [];

        if (! in_array($declared->name, $listed, true)) {
            $this->names[$reduction->value] = [...$listed, $declared->name];
        }
    }

    public function narrowed(): NarrowedFields
    {
        return new NarrowedFields(
            $this->listed(FieldReduction::Hide),
            $this->listed(FieldReduction::ReadOnly),
            $this->listed(FieldReduction::Unfilterable),
            $this->listed(FieldReduction::Unsortable),
        );
    }

    private function ensureHideable(string $extension, Field $field): void
    {
        $resource = $this->resource::class;

        if ($field->name === $this->version) {
            throw InvalidFieldExtensionException::hidesVersion($extension, $resource, $field->name);
        }

        if ($field->name === $this->key) {
            throw InvalidFieldExtensionException::hidesKey($extension, $resource, $field->name);
        }

        $text = $this->text();

        if ($text instanceof QueryTextSearch && $field->isSearchable()) {
            throw InvalidFieldExtensionException::hidesSearchable($extension, $resource, $field->name);
        }

        if ($text instanceof ScoutEngine && in_array($field->name, $text->perimeterNames(), true)) {
            throw InvalidFieldExtensionException::hidesPerimeterAttribute($extension, $resource, $field->name);
        }
    }

    private function text(): ?TextSearch
    {
        if (! $this->textRead) {
            $this->text = $this->resource->text();
            $this->textRead = true;
        }

        return $this->text;
    }

    /** @return list<string> */
    private function listed(FieldReduction $reduction): array
    {
        return $this->names[$reduction->value] ?? [];
    }
}
