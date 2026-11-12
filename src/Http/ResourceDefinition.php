<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Authorization\CachedAbilities;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Mutations\RowVersion;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Search\GateVocabulary;
use Lomkit\Rest\Text\ResolvedText;
use ReflectionClass;
use WeakMap;

final class ResourceDefinition
{
    private ?FieldSet $fields = null;

    private ?RowVersion $version = null;

    /** @var list<Scope>|null */
    private ?array $scopes = null;

    /** @var array<int, Relation>|null */
    private ?array $relations = null;

    /** @var array<int, Instruction>|null */
    private ?array $instructions = null;

    private ?ResourceLimits $limits = null;

    private ?ResolvedText $text = null;

    private ?GateVocabulary $gates = null;

    private ?CachedAbilities $cachedAbilities = null;

    /** @var WeakMap<Action|Instruction, FieldSet> */
    private WeakMap $inputs;

    private readonly ResourceDeclarations $declarations;

    public function __construct(
        private readonly Resource $resource,
        private readonly RestRequest $request,
        Container $container,
    ) {
        $this->inputs = new WeakMap;
        $this->declarations = new ResourceDeclarations($resource, $container);
    }

    public function fields(): FieldSet
    {
        return $this->fields ??= $this->declaredFields();
    }

    public function version(): ?RowVersion
    {
        $this->fields();

        return $this->version;
    }

    /** @return list<Scope> */
    public function scopes(): array
    {
        return $this->scopes ??= Scope::listOf($this->resource->scopes(), $this->resource::class.'::scopes()');
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

    /** @internal */
    public function declarations(): ResourceDeclarations
    {
        return $this->declarations;
    }

    /** @return array<int, Action> */
    public function actions(): array
    {
        return $this->declarations->actions()->all();
    }

    /** @return array<int, Metric> */
    public function metrics(): array
    {
        return $this->declarations->metrics()->all();
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
        return $this->declarations->actions()->find($key) !== null;
    }

    public function action(string $key): Action
    {
        return $this->declarations->actions()->find($key) ?? throw new ItemNotFoundException;
    }

    public function metric(string $key): ?Metric
    {
        return $this->declarations->metrics()->find($key);
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
        return $this->gates ??= GateVocabulary::of($this->resource->gates(), $this->resource::class.'::gates()', Trashed::supportedBy($this->resource::modelClass()));
    }

    public function cachedAbilities(): CachedAbilities
    {
        return $this->cachedAbilities ??= CachedAbilities::of($this->resource->authorizationCache(), $this->resource::class, $this->resource->authorizationCacheContext(...));
    }

    private function declaredFields(): FieldSet
    {
        $fields = FieldSet::of($this->resource->fields(), $this->resource::class.'::fields()');
        $config = $this->resource->context()->config();

        if ($fields->has($config->gatesKey)) {
            throw InvalidResourceException::gatesKeyField($this->resource::class, $config->gatesKey);
        }

        if ($fields->has($config->moreKey)) {
            throw InvalidResourceException::moreKeyField($this->resource::class, $config->moreKey);
        }

        if (! (new ReflectionClass($this->resource::modelClass()))->isInstantiable()) {
            return $fields;
        }

        $model = $this->resource::newModel();
        $bound = $fields->bindTo($model);

        if ($this->resource->versioned()) {
            $this->version = RowVersion::of($this->resource, $model, $bound);
        }

        return $bound;
    }

    /**
     * @param  array<int, Instruction>  $instructions
     * @return array<int, Instruction>
     */
    private function boundInstructions(array $instructions): array
    {
        return array_map(fn (Instruction $instruction): Instruction => $instruction->resource($this->resource), $instructions);
    }
}
