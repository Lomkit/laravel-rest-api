<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Mutations\LinkReplay;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\RowSet;
use Lomkit\Rest\Mutations\VersionClaims;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Search\SearchNode;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Mutate\MutationParser;
use Lomkit\Rest\Validation\Mutate\ResponseBlockParser;

class MutateRequest extends RestRequest
{
    /** @var list<MutationNode>|null */
    private ?array $mutations = null;

    private ?SearchNode $responseBlock = null;

    private RowSet $rows;

    private VersionClaims $versions;

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

        if ($this->mutations === null) {
            $input = Cursor::of($this->all(), $this->parsedRules()->violations());
            $this->rows = new RowSet($this->reachLog());
            $this->versions = new VersionClaims;
            $links = new LinkReplay($this->rows);
            $this->mutations = MutationParser::of($this, $this->parsedRules(), $this->budget(), $this->rows, $links, $this->versions)
                ->parse($input->at('mutate'), $resource);
            $this->responseBlock = (new ResponseBlockParser($this->parsedRules(), $this->budget()))->parse($input->at('response'), $resource, self::entries($input->at('mutate')));

            if (! $this->parsedRules()->isPartial()) {
                $links->plan($resource, $this->mutations);
            }
        }

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

    public function responseBlock(): ?SearchNode
    {
        $this->payload();

        return $this->responseBlock;
    }

    public function rows(): RowSet
    {
        $this->payload();

        return $this->rows;
    }

    public function versions(): VersionClaims
    {
        $this->payload();

        return $this->versions;
    }

    /** @return EloquentBuilder<Model> */
    public function keysQuery(Resource $resource): EloquentBuilder
    {
        $query = $resource::newModel()->newQuery();

        (new PerimeterClause($resource))->apply($query);

        return $query;
    }

    private static function entries(Cursor $mutate): int
    {
        return is_array($mutate->value) ? count($mutate->value) : 0;
    }
}
