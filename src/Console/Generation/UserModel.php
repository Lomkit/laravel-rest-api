<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Contracts\Config\Repository;

final readonly class UserModel
{
    public static function configured(Repository $config, string $rootNamespace): string
    {
        $model = $config->get('auth.providers.'.self::provider($config).'.model');

        return is_string($model) && $model !== '' ? ltrim($model, '\\') : $rootNamespace.'Models\User';
    }

    private static function provider(Repository $config): string
    {
        $guard = $config->get('auth.defaults.guard');
        $provider = is_string($guard) ? $config->get("auth.guards.{$guard}.provider") : null;

        return is_string($provider) ? $provider : 'users';
    }
}
