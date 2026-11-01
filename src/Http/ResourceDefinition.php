<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Exceptions\InvalidScopeException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Text\ResolvedText;
use ReflectionClass;

final class ResourceDefinition
{
    private ?FieldSet $fields = null;

    /** @var list<Scope>|null */
    private ?array $scopes = null;

    /** @var array<int, Relation>|null */
    private ?array $relations = null;

    /** @var array<int, Instruction>|null */
    private ?array $instructions = null;

    /** @var array<int, Action>|null */
    private ?array $actions = null;

    /** @var list<int>|null */
    private ?array $limits = null;

    private ?ResolvedText $text = null;

    public function __construct(
        private readonly Resource $resource,
        private readonly RestRequest $request,
    ) {
        RemovedHooks::ensureAbsentFrom($resource);
    }

    public function fields(): FieldSet
    {
        return $this->fields ??= $this->declaredFields();
    }

    /** @return list<Scope> */
    public function scopes(): array
    {
        return $this->scopes ??= self::ensureScopes($this->resource->scopes($this->request), $this->resource::class.'::scopes()');
    }

    /** @return array<int, Relation> */
    public function relations(): array
    {
        return $this->relations ??= array_map(
            fn (Relation $relation): Relation => $relation->fromResource($this->resource),
            $this->resource->relations($this->request),
        );
    }

    /** @return array<int, Instruction> */
    public function instructions(): array
    {
        return $this->instructions ??= $this->boundInstructions($this->resource->instructions($this->request));
    }

    /** @return array<int, Action> */
    public function actions(): array
    {
        return $this->actions ??= array_map(
            fn (Action $action): Action => $action->resource($this->resource),
            $this->resource->actions($this->request),
        );
    }

    /** @return list<int> */
    public function limits(): array
    {
        return $this->limits ??= $this->resource->limits($this->request);
    }

    public function text(): ResolvedText
    {
        return $this->text ??= ResolvedText::of($this->resource, $this->request);
    }

    private function declaredFields(): FieldSet
    {
        $fields = FieldSet::of($this->resource->fields($this->request), $this->resource::class.'::fields()');

        if (! (new ReflectionClass($this->resource::modelClass()))->isInstantiable()) {
            return $fields;
        }

        return $fields->bindTo($this->resource::newModel());
    }

    /**
     * @param  array<int, Instruction>  $instructions
     * @return array<int, Instruction>
     */
    private function boundInstructions(array $instructions): array
    {
        return array_map(function (Instruction $instruction): Instruction {
            RemovedHooks::ensureAbsentFromInstruction($instruction);

            return $instruction->resource($this->resource);
        }, $instructions);
    }

    /**
     * @param  array<mixed>  $scopes
     * @return list<Scope>
     */
    private static function ensureScopes(array $scopes, string $owner): array
    {
        $declared = [];

        foreach (array_values($scopes) as $index => $scope) {
            if (! $scope instanceof Scope) {
                throw InvalidScopeException::notAScope($owner, $index, $scope);
            }

            $declared[] = $scope;
        }

        return $declared;
    }
}
