<?php

namespace Lomkit\Rest\Http;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Actions\Actionable;
use Lomkit\Rest\Concerns\Authorizable;
use Lomkit\Rest\Concerns\PerformsModelOperations;
use Lomkit\Rest\Concerns\PerformsQueries;
use Lomkit\Rest\Concerns\Resource\ConfiguresRestParameters;
use Lomkit\Rest\Concerns\Resource\Paginable;
use Lomkit\Rest\Concerns\Resource\Relationable;
use Lomkit\Rest\Concerns\Resource\Rulable;
use Lomkit\Rest\Http\Requests\RestRequest;

class Resource
{
    use PerformsQueries,
        PerformsModelOperations,
        Relationable,
        Paginable,
        Rulable,
        ConfiguresRestParameters,
        Authorizable,
        Actionable;

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

        return new $model;
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

        return new $response;
    }

    public function defaultOrderBy(RestRequest $request): array {
        return [
            'id' => 'desc'
        ];
    }

    public function isAutomaticGatingEnabled() : bool {
        return config('rest.automatic_gates.enabled');
    }

    public function isAuthorizingEnabled() : bool {
        return config('rest.authorizations.enabled');
    }
}