<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Actions\Actionable;
use Lomkit\Rest\Authorization\ClassAuthorizations;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Concerns\PerformsModelOperations;
use Lomkit\Rest\Concerns\PerformsQueries;
use Lomkit\Rest\Concerns\Resource\Contextual;
use Lomkit\Rest\Concerns\Resource\DeclaresFields;
use Lomkit\Rest\Concerns\Resource\DeclaresScopes;
use Lomkit\Rest\Concerns\Resource\HasEndpointEvents;
use Lomkit\Rest\Concerns\Resource\HasResourceHooks;
use Lomkit\Rest\Concerns\Resource\Paginable;
use Lomkit\Rest\Concerns\Resource\Relationable;
use Lomkit\Rest\Concerns\Resource\Rulable;
use Lomkit\Rest\Concerns\Resource\SearchesText;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Fields\Details\FieldDetails;
use Lomkit\Rest\Fields\Details\ResourceRuleDetails;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\Instructionable;
use Lomkit\Rest\Metrics\HasMetrics;
use Lomkit\Rest\Metrics\Metric;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Scopes\Scope;

class Resource implements \JsonSerializable
{
    use Actionable;
    use Authorizable;
    use Contextual;
    use DeclaresFields;
    use DeclaresScopes;
    use HasEndpointEvents;
    use HasMetrics;
    use HasResourceHooks;
    use Instructionable;
    use Paginable;
    use PerformsModelOperations;
    use PerformsQueries;
    use Relationable;
    use Rulable;
    use SearchesText;

    /** @var class-string<Model> */
    public static string $model;

    /** @var class-string<Response> */
    public static string $response = Response::class;

    public int $defaultLimit = 50;

    public int $includeLimit = 10;

    /** @return class-string<Model> */
    public static function modelClass(): string
    {
        if (! isset(static::$model)) {
            throw InvalidResourceException::missingModel(static::class);
        }

        return static::$model;
    }

    public static function newModel(): Model
    {
        $model = static::modelClass();

        return new $model;
    }

    public static function newResponse(): Response
    {
        $response = static::$response;

        return new $response;
    }

    /** @return array<string, string> */
    public function defaultOrderBy(): array
    {
        return [
            static::newModel()->getKeyName() => 'desc',
        ];
    }

    public function isGatingEnabled(): bool
    {
        return true;
    }

    public function isAuthorizingEnabled(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): mixed
    {
        $definition = $this->definition();
        $authorizations = ClassAuthorizations::of($this);

        return [
            'actions' => array_values(array_map(fn (Action $action): array => [...$action->jsonSerialize(), 'authorized' => $authorizations->allow($action->entryAbilities())], $definition->actions())),
            'metrics' => array_values(array_map(fn (Metric $metric): array => [...$metric->jsonSerialize(), 'authorized' => $authorizations->allow($metric->entryAbilities())], $definition->metrics())),
            'instructions' => array_values(array_map(fn (Instruction $instruction): array => $instruction->jsonSerialize(), $definition->instructions())),
            'fields' => FieldDetails::forFields($definition->fields()),
            'limits' => $definition->limits()->allowed,
            'scopes' => array_map(fn (Scope $scope): array => $scope->jsonSerialize(), $definition->scopes()),
            'relations' => array_values(array_map(fn (Relation $relation): mixed => $relation->jsonSerialize(), $definition->relations())),
            'rules' => ResourceRuleDetails::of($this)->jsonSerialize(),
            'text' => $this->context()->text($this)->capabilities?->jsonSerialize(),
            'gates' => $this->isGatingEnabled() ? $definition->gates()->names() : [],
            'authorizations' => $authorizations->jsonSerialize(),
        ];
    }
}
