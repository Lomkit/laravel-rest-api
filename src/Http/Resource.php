<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Laravel\Scout\Searchable;
use Lomkit\Rest\Actions\Actionable;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Concerns\PerformsModelOperations;
use Lomkit\Rest\Concerns\PerformsQueries;
use Lomkit\Rest\Concerns\Resource\ConfiguresRestParameters;
use Lomkit\Rest\Concerns\Resource\HasResourceHooks;
use Lomkit\Rest\Concerns\Resource\Paginable;
use Lomkit\Rest\Concerns\Resource\Relationable;
use Lomkit\Rest\Concerns\Resource\Rulable;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Instructions\Instructionable;

class Resource implements \JsonSerializable
{
    use PerformsQueries;
    use PerformsModelOperations;
    use Relationable;
    use Paginable;
    use Rulable;
    use ConfiguresRestParameters;
    use Authorizable;
    use Actionable;
    use Instructionable;
    use HasResourceHooks;

    /**
     * The model the entry corresponds to.
     *
     * @var class-string<Model>
     */
    public static $model;

    /**
     * The reponse the entry corresponds to.
     *
     * @var class-string<Response>
     */
    public static $response = Response::class;

    /**
     * Get a fresh instance of the model represented by the resource.
     *
     * @return Model
     */
    public static function newModel()
    {
        /** @var Model $model */
        $model = static::$model;

        return new $model();
    }

    /**
     * Get a fresh instance of the model represented by the resource.
     *
     * @return Response
     */
    public static function newResponse()
    {
        /** @var Response $response */
        $response = static::$response;

        return new $response();
    }

    /**
     * Return the default ordering for resource queries.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function defaultOrderBy(RestRequest $request): array
    {
        return [
            'id' => 'desc',
        ];
    }

    /**
     * Check if gating is enabled for this resource.
     *
     * @return bool
     */
    public function isGatingEnabled(): bool
    {
        return config('rest.gates.enabled', true);
    }

    /**
     * Check if authorizations are enabled for this resource.
     *
     * @return bool
     */
    public function isAuthorizingEnabled(): bool
    {
        return config('rest.authorizations.enabled', true);
    }

    /**
     * Check if authorization cache is enabled for this resource.
     *
     * @return bool
     */
    public function isAuthorizationCacheEnabled(): bool
    {
        return config('rest.authorizations.cache.enabled', true);
    }

    /**
     * Get the authorization cache key.
     *
     * @param RestRequest $request
     *
     * @return string
     */
    public function getAuthorizationCacheKey(RestRequest $request, string $identifier)
    {
        $class = Str::snake((new \ReflectionClass($this))->getShortName());

        return sprintf(
            'rest.authorization.%s.%s.%s',
            $class,
            $identifier,
            $request->user()?->getKey()
        );
    }

    /**
     * Determine for how much time the authorization cache should be keeped.
     *
     * @return \DateTimeInterface|\DateInterval
     */
    public function cacheAuthorizationFor()
    {
        return now()->addMinutes(config('rest.authorizations.cache.default', 5));
    }

    public function isModelSearchable()
    {
        return in_array(Searchable::class, class_uses_recursive(static::$model));
    }

    /**
     * Serialize the resource into a JSON-serializable format.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        $request = app(RestRequest::class);

        return [
            'actions'      => collect($this->getActions($request))->map->jsonSerialize()->toArray(),
            'instructions' => collect($this->getInstructions($request))->map->jsonSerialize()->toArray(),
            'fields'       => $this->getFields($request),
            'limits'       => $this->getLimits($request),
            'scopes'       => $this->getScopes($request),
            'relations'    => collect($this->getRelations($request))->map->jsonSerialize()->toArray(),
            'rules'        => [
                'all'    => $this->rules($request),
                'create' => $this->createRules($request),
                'update' => $this->updateRules($request),
            ],
        ];
    }
}
