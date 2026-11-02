<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Mutate\MutationParser;

class MutateRequest extends RestRequest
{
    /** @var list<MutationNode>|null */
    private ?array $mutations = null;

    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Mutate;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        $this->resource($resource = $this->context()->resource($this->controllerResourceClass()));

        $this->mutations ??= MutationParser::of($this, $this->parsedRules(), $this->budget())
            ->parse(Cursor::of($this->all(), $this->parsedRules()->violations())->at('mutate'), $resource);

        return $this->parsedRules()->rules();
    }

    /** @return list<MutationNode> */
    public function payload(): array
    {
        $rules = $this->parsedRules();

        if ($this->mutations === null || ! $rules->violations()->isEmpty() || $rules->isPartial() || ! $this->hasPassedValidation()) {
            throw new LogicException('The mutation was read before the request passed validation.');
        }

        return $this->mutations;
    }

    /** @return EloquentBuilder<Model> */
    public function keysQuery(Resource $resource): EloquentBuilder
    {
        return $resource::newModel()
            ->newQuery()
            ->where(fn (Builder $query): Builder => $resource->mutateQuery($this, $query));
    }
}
