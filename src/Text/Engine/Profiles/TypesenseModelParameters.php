<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Illuminate\Database\Eloquent\Model;

/** @internal */
final readonly class TypesenseModelParameters
{
    /** @param list<string> $options */
    public static function owned(Model $model, array $options): ?string
    {
        $parameters = self::of($model);
        $owned = is_array($parameters) ? array_values(array_intersect($options, array_keys($parameters))) : [];

        return $owned[0] ?? null;
    }

    public static function queryBy(Model $model): mixed
    {
        $parameters = self::of($model);

        if (is_array($parameters) && isset($parameters['query_by'])) {
            return $parameters['query_by'];
        }

        return config('scout.typesense.model-settings.'.$model::class.'.search-parameters.query_by');
    }

    private static function of(Model $model): mixed
    {
        return method_exists($model, 'typesenseSearchParameters') ? $model->typesenseSearchParameters() : null;
    }
}
