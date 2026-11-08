<?php

namespace Lomkit\Rest\Validation\Operate;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Actions\OperationNode;
use Lomkit\Rest\Fields\InputValues;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\InputSet;
use Lomkit\Rest\Validation\KeyList;
use Lomkit\Rest\Validation\KeysGrammar;
use Lomkit\Rest\Validation\ReachLog;
use Lomkit\Rest\Validation\Search\SearchParser;
use Lomkit\Rest\Validation\Search\SearchShape;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class OperationParser
{
    /** @param Closure(): Builder<Model> $keysQuery */
    public function __construct(
        private Closure $keysQuery,
        private ReachLog $reach,
    ) {}

    public function parse(Cursor $root, Action $action, ValidationContext $context): OperationNode
    {
        $target = $this->target($root, $action, $context);
        $context->rules->addWhenPresent($root->at('confirmation'), ['string']);

        return new OperationNode($action, $this->fields($root->at('fields'), $action, $context), $target);
    }

    private function target(Cursor $root, Action $action, ValidationContext $context): SearchNode|KeyList|null
    {
        $search = $root->at('search');
        $resources = $root->at('resources');

        return match ($action->target) {
            ActionTarget::Search => $this->search($search, $resources, $context),
            ActionTarget::Resources => $this->keys($search, $resources, $action->maxResources, $context),
            ActionTarget::None => $this->none($search, $resources, $context),
        };
    }

    private function search(Cursor $search, Cursor $resources, ValidationContext $context): SearchNode
    {
        if ($search->value === null) {
            $context->rules->add($search->path, ['required']);
        }

        $node = (new SearchParser(SearchShape::Rows))->parse($search, $context);
        $context->rules->add($resources->path, ['prohibited']);

        return $node;
    }

    private function none(Cursor $search, Cursor $resources, ValidationContext $context): null
    {
        $context->rules->add($search->path, ['prohibited']);
        $context->rules->add($resources->path, ['prohibited']);

        return null;
    }

    private function keys(Cursor $search, Cursor $resources, ?int $max, ValidationContext $context): KeyList
    {
        $rules = $context->rules;
        $rules->add($search->path, ['prohibited']);
        $keys = KeysGrammar::of($context->resource, $this->keysQuery, $max, log: $this->reach)->parse($resources);

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
        $set = $context->resource->definition()->inputFields($action);

        if ($fields->present && ! is_array($fields->value)) {
            $context->rules->partial();

            return InputValues::fromNamed($set, []);
        }

        return (new InputSet($set))->inputsFirst($fields, $context, 'action-fields:'.$action->uriKey());
    }
}
