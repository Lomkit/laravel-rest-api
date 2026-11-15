<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ItemNotFoundException;
use Illuminate\Support\Str;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Authorization\CacheContext;
use Lomkit\Rest\Authorization\CachedAbilities;
use Lomkit\Rest\Contracts\PolymorphicRelation;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\NarrowedFields;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Mutations\RowVersion;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scopes\Scope;
use Lomkit\Rest\Search\GateVocabulary;
use Lomkit\Rest\Text\ResolvedText;

final class ResourceDefinition
{
    /** @var list<Scope>|null */
    private ?array $scopes = null;

    /** @var array<int, Relation>|null */
    private ?array $relations = null;

    private ?ResourceLimits $limits = null;

    /** @var list<string>|null */
    private ?array $alwaysSelect = null;

    private ?ResolvedText $text = null;

    private readonly ResourceDeclarations $declarations;

    private readonly ResourceFields $fields;

    private readonly ResourceAuthorization $authorization;

    public function __construct(
        private readonly Resource $resource,
        private readonly RestRequest $request,
        Container $container,
    ) {
        $this->declarations = new ResourceDeclarations($resource, $container);
        $this->fields = new ResourceFields($resource);
        $this->authorization = new ResourceAuthorization($resource);
    }

    public function fields(): FieldSet
    {
        return $this->fields->fields();
    }

    /** @internal */
    public function declaredFields(): FieldSet
    {
        return $this->fields->declared();
    }

    /** @internal */
    public function fieldNarrowing(): NarrowedFields
    {
        return $this->fields->narrowing();
    }

    public function version(): ?RowVersion
    {
        return $this->fields->version();
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
        return $this->declarations->instructions()->all();
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
        return $this->declarations->instructions()->find($key);
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
        return $this->declarations->inputFields($declaration);
    }

    /** @return list<string> */
    public function alwaysSelect(): array
    {
        return $this->alwaysSelect ??= $this->resource->alwaysSelect();
    }

    public function limits(): ResourceLimits
    {
        return $this->limits ??= LimitNarrowing::of($this->declaredLimits(), $this->resource);
    }

    public function declaredLimits(): ResourceLimits
    {
        return ResourceLimits::of($this->resource, $this->resource->limits());
    }

    public function text(): ResolvedText
    {
        return $this->text ??= ResolvedText::of($this->resource, $this->request);
    }

    public function gates(): GateVocabulary
    {
        return $this->authorization->gates();
    }

    public function cachedAbilities(): CachedAbilities
    {
        return $this->authorization->cachedAbilities();
    }

    /** @internal */
    public function cacheContext(): CacheContext
    {
        return $this->authorization->cacheContext();
    }
}
