<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\OperationNode;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Fields\CastValues;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Query\Clauses\PerimeterClause;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\Operate\OperationParser;
use Lomkit\Rest\Validation\ValidationContext;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OperateRequest extends RestRequest
{
    private ?OperationNode $operation = null;

    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Operate;
    }

    #[\Override]
    protected function authorizeEntry(Authorizer $authorizer): void
    {
        $resource = $this->rootResource();
        $key = $this->route('action');

        if (! is_string($key) || ! $resource->definition()->actionExists($key)) {
            $authorizer->plan([Check::onClass($resource, 'viewAny')]);

            throw new HttpException(404);
        }

        $action = $resource->definition()->action($key);

        if ($action->abilities() !== []) {
            $authorizer->plan([Check::onClass($resource, 'viewAny')]);
        }

        $authorizer->enter($resource, self::checksOn($resource, $this->entryAbilities($resource, $action)));
    }

    /** @return list<string> */
    private function entryAbilities(Resource $resource, Action $action): array
    {
        $abilities = $action->abilities();

        return $abilities === [] ? [] : ['viewAny', ...$resource->abilities(Endpoint::Operate), ...$abilities];
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function rules(): array
    {
        $this->resource($resource = $this->context()->resource($this->controllerResourceClass()));

        $this->operation ??= $this->parse($resource);

        return $this->parsedRules()->rules();
    }

    public function payload(): OperationNode
    {
        $rules = $this->parsedRules();

        if ($this->operation === null || ! $rules->violations()->isEmpty() || $rules->isPartial() || ! $this->hasPassedValidation()) {
            throw new LogicException('The operation was read before the request passed validation.');
        }

        return $this->operation;
    }

    /** @return array<string, mixed> */
    public function resolveFields(): array
    {
        $operation = $this->payload();

        return CastValues::of($operation->fields, $operation->action::class.'::fields()')->toArray();
    }

    /** @return Builder<Model> */
    public function keysQuery(Resource $resource): Builder
    {
        $query = $resource::newModel()->newQuery();

        (new PerimeterClause($resource))->apply($query);

        return $query;
    }

    public function actionKey(): string
    {
        $key = $this->route('action');

        if (! is_string($key)) {
            throw new HttpException(404);
        }

        return $key;
    }

    private function parse(Resource $resource): OperationNode
    {
        if (! $resource->definition()->actionExists($this->actionKey())) {
            throw new HttpException(404);
        }

        return (new OperationParser(fn (): Builder => $this->keysQuery($resource)))->parse(
            Cursor::of($this->all(), $this->parsedRules()->violations()),
            $resource->definition()->action($this->actionKey()),
            ValidationContext::root($resource, $this->budget(), $this->parsedRules()),
        );
    }
}
