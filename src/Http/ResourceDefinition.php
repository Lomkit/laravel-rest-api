<?php

namespace Lomkit\Rest\Http;

use Illuminate\Support\Str;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Authorization\CachedAbilities;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Exceptions\InvalidScopeException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Search\GateVocabulary;
use Lomkit\Rest\Text\ResolvedText;
use ReflectionClass;
use WeakMap;

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

    private ?ResourceLimits $limits = null;

    private ?ResolvedText $text = null;

    private ?GateVocabulary $gates = null;

    private ?CachedAbilities $cachedAbilities = null;

    /** @var WeakMap<Action|Instruction, FieldSet> */
    private WeakMap $inputs;

    public function __construct(
        private readonly Resource $resource,
        private readonly RestRequest $request,
    ) {
        $this->inputs = new WeakMap;
    }

    public function fields(): FieldSet
    {
        return $this->fields ??= $this->declaredFields();
    }

    /** @return list<Scope> */
    public function scopes(): array
    {
        return $this->scopes ??= self::ensureScopes($this->resource->scopes(), $this->resource::class.'::scopes()');
    }

    /** @return array<int, Relation> */
    public function relations(): array
    {
        return $this->relations ??= array_map(
            fn (Relation $relation): Relation => $relation->fromResource($this->resource),
            $this->resource->relations(),
        );
    }

    /** @return array<int, Instruction> */
    public function instructions(): array
    {
        return $this->instructions ??= $this->boundInstructions($this->resource->instructions());
    }

    /** @return array<int, Action> */
    public function actions(): array
    {
        return $this->actions ??= array_map(
            fn (Action $action): Action => $action->resource($this->resource)->ensureValid(),
            $this->resource->actions(),
        );
    }

    public function scope(string $name): ?Scope
    {
        return array_find($this->scopes(), fn (Scope $scope): bool => $scope->name === $name);
    }

    public function relation(string $name): ?Relation
    {
        $relation = array_find($this->relations(), fn (Relation $relation): bool => $relation->relation === Str::before($name, '.'));

        if ($relation === null || ! str_contains($name, '.')) {
            return $relation;
        }

        if ($relation instanceof PolymorphicRelation && $relation->targets()->isPolymorphic()) {
            return null;
        }

        return $relation->resource()
            ->definition()
            ->relation(Str::after($name, '.'));
    }

    public function instruction(string $key): ?Instruction
    {
        return array_find($this->instructions(), fn (Instruction $instruction): bool => $instruction->uriKey() === $key);
    }

    public function actionExists(string $key): bool
    {
        return array_any($this->actions(), fn (Action $action): bool => $action->uriKey() === $key);
    }

    public function action(string $key): Action
    {
        return collect($this->actions())->sole(fn (Action $action): bool => $action->uriKey() === $key);
    }

    public function inputFields(Action|Instruction $declaration): FieldSet
    {
        return $this->inputs[$declaration] ??= FieldSet::forInputs($declaration->fields(), $declaration::class.'::fields()');
    }

    public function limits(): ResourceLimits
    {
        return $this->limits ??= ResourceLimits::of($this->resource, $this->resource->limits());
    }

    public function text(): ResolvedText
    {
        return $this->text ??= ResolvedText::of($this->resource, $this->request);
    }

    public function gates(): GateVocabulary
    {
        return $this->gates ??= GateVocabulary::of($this->resource->gates(), $this->resource::class.'::gates()');
    }

    public function cachedAbilities(): CachedAbilities
    {
        return $this->cachedAbilities ??= CachedAbilities::of($this->resource->authorizationCache(), $this->resource::class, $this->resource->authorizationCacheContext(...));
    }

    private function declaredFields(): FieldSet
    {
        $fields = FieldSet::of($this->resource->fields(), $this->resource::class.'::fields()');
        $gatesKey = $this->resource->context()->config()->gatesKey;

        if ($fields->has($gatesKey)) {
            throw InvalidResourceException::gatesKeyField($this->resource::class, $gatesKey);
        }

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
        return array_map(fn (Instruction $instruction): Instruction => $instruction->resource($this->resource), $instructions);
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
