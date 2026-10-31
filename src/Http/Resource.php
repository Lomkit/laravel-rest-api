<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use Lomkit\Rest\Actions\Actionable;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Concerns\PerformsModelOperations;
use Lomkit\Rest\Concerns\PerformsQueries;
use Lomkit\Rest\Concerns\Resource\ConfiguresRestParameters;
use Lomkit\Rest\Concerns\Resource\Contextual;
use Lomkit\Rest\Concerns\Resource\DeclaresFields;
use Lomkit\Rest\Concerns\Resource\HasResourceHooks;
use Lomkit\Rest\Concerns\Resource\Paginable;
use Lomkit\Rest\Concerns\Resource\Relationable;
use Lomkit\Rest\Concerns\Resource\Rulable;
use Lomkit\Rest\Concerns\Resource\Scoutable;
use Lomkit\Rest\Concerns\Resource\SearchesText;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instructionable;
use Lomkit\Rest\Scopes\Scope;

class Resource implements \JsonSerializable
{
    use Actionable;
    use Authorizable;
    use ConfiguresRestParameters;
    use Contextual;
    use DeclaresFields;
    use HasResourceHooks;
    use Instructionable;
    use Paginable;
    use PerformsModelOperations;
    use PerformsQueries;
    use Relationable;
    use Rulable;
    use Scoutable;
    use SearchesText;

    /** @var class-string<Model> */
    public static string $model;

    /** @var class-string<Response> */
    public static string $response = Response::class;

    public int $defaultLimit = 50;

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
    public function defaultOrderBy(RestRequest $request): array
    {
        return [
            static::newModel()->getKeyName() => 'desc',
        ];
    }

    public function isGatingEnabled(): bool
    {
        return config('rest.gates.enabled', true);
    }

    public function isAuthorizingEnabled(): bool
    {
        return config('rest.authorizations.enabled', true);
    }

    public function isAuthorizationCacheEnabled(): bool
    {
        return config('rest.authorizations.cache.enabled', false);
    }

    /** @return \DateTimeInterface|\DateInterval */
    public function cacheAuthorizationFor()
    {
        return now()->addMinutes(config('rest.authorizations.cache.default', 5));
    }

    public function isModelSearchable(): bool
    {
        return in_array(Searchable::class, class_uses_recursive(static::modelClass()));
    }

    #[\Override]
    public function jsonSerialize(): mixed
    {
        $request = $this->context()->request;

        return [
            'actions' => collect($this->getActions($request))->map->jsonSerialize()->toArray(),
            'instructions' => collect($this->getInstructions($request))->map->jsonSerialize()->toArray(),
            'scout_instructions' => collect($this->getScoutInstructions($request))->map->jsonSerialize()->toArray(),
            'fields' => $this->getFields($request)->names(),
            'scout_fields' => $this->getScoutFields($request),
            'limits' => $this->getLimits($request),
            'scopes' => array_map(fn (Scope $scope): array => $scope->jsonSerialize(), $this->getScopes($request)),
            'relations' => collect($this->getRelations($request))->map->jsonSerialize()->toArray(),
            'rules' => [
                'all' => $this->rules($request),
                'create' => $this->createRules($request),
                'update' => $this->updateRules($request),
            ],
        ];
    }
}
