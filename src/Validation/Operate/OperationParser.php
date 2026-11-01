<?php

namespace Lomkit\Rest\Validation\Operate;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\OperationNode;
use Lomkit\Rest\Exceptions\InvalidActionStateException;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\InputSet;
use Lomkit\Rest\Validation\KeyList;
use Lomkit\Rest\Validation\KeysGrammar;
use Lomkit\Rest\Validation\Search\SearchParser;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class OperationParser
{
    /** @param Closure(): Builder<Model> $keysQuery */
    public function __construct(private Closure $keysQuery) {}

    public function parse(Cursor $root, Action $action, ValidationContext $context): OperationNode
    {
        if ($action->isStandalone() && $action->isTargeted()) {
            throw new InvalidActionStateException('An action cannot be both standalone and targeted.');
        }

        $target = $this->target($root, $action, $context);

        return new OperationNode($action, $this->fields($root->at('fields'), $action, $context), $target);
    }

    private function target(Cursor $root, Action $action, ValidationContext $context): SearchNode|KeyList|null
    {
        $search = $root->at('search');
        $resources = $root->at('resources');

        if (! $action->isStandalone() && ! $action->isTargeted()) {
            $node = (new SearchParser(paged: false))->parse($search, $context);
            $context->rules->add($resources->path, ['prohibited']);

            return $node;
        }

        $context->rules->add($search->path, ['prohibited']);

        if ($action->isTargeted()) {
            return $this->keys($resources, $action->maxResources, $context);
        }

        $context->rules->add($resources->path, ['prohibited']);

        return null;
    }

    private function keys(Cursor $resources, int $max, ValidationContext $context): KeyList
    {
        $rules = $context->rules;
        $keys = KeysGrammar::of($context->resource, $context->resource->context()->request, $this->keysQuery, $max)->parse($resources);

        if ($rules->violations()->has($resources->path)) {
            $context->reader->close($resources);
        }

        $rules->add($resources->path, ['required', 'array']);

        foreach ($keys->rules as $key => $list) {
            $rules->add($key, $list);
        }

        if (! is_array($resources->value) || count($keys->keys) !== count($resources->value)) {
            $rules->partial();
        }

        return $keys;
    }

    private function fields(Cursor $fields, Action $action, ValidationContext $context): InputValues
    {
        $context->rules->addWhenPresent($fields, ['array']);
        $context->reader->admit($fields, $context->limits->sizes->fields);
        $set = $action->getFields($context->resource->context()->request);

        if ($fields->present && ! is_array($fields->value)) {
            $context->rules->partial();

            return InputValues::fromNamed($set, []);
        }

        return (new InputSet($set))->inputsFirst($fields, $context, 'action-fields:'.$action->uriKey());
    }
}
