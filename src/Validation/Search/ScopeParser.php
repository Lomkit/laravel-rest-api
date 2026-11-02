<?php

namespace Lomkit\Rest\Validation\Search;

use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Search\ScopeNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\InputSet;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class ScopeParser
{
    private const array KEYS = ['name' => true, 'parameters' => true];

    /**
     * @param  list<Cursor>  $entries
     * @return list<ScopeNode>
     */
    public function parse(array $entries, ValidationContext $context): array
    {
        if ($entries === []) {
            return [];
        }

        $resource = $context->resource;
        $names = $context->vocabulary->of($resource, $context->grammar, 'scopes', fn (): array => array_map(
            fn (Scope $scope): string => $scope->name,
            $resource->getScopes($resource->context()->request),
        ));
        $scopes = [];

        foreach ($entries as $entry) {
            $scope = $this->scope($entry, $names, $context);

            if ($scope !== null) {
                $scopes[] = $scope;
            }
        }

        return $scopes;
    }

    private function scope(Cursor $entry, OneOf $names, ValidationContext $context): ?ScopeNode
    {
        if (! $context->reader->object($entry)) {
            return null;
        }

        $context->reader->known($entry, self::KEYS);

        $resource = $context->resource;
        $name = $entry->at('name');
        $parameters = $entry->at('parameters');

        $context->rules->add($name->path, ['required', 'string', $names]);

        $scope = is_string($name->value) ? $resource->scope($resource->context()->request, $name->value) : null;

        if ($scope === null) {
            $context->rules->addWhenPresent($parameters, ['array', 'list']);
            $context->rules->partial();

            return null;
        }

        return new ScopeNode($scope, (new InputSet($scope->getFields()))->positional($parameters, $context));
    }
}
