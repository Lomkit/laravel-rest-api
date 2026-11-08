<?php

namespace Lomkit\Rest\Events;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\ActingUser;
use Lomkit\Rest\Exceptions\InvalidUserException;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;
use Stringable;

final readonly class KeysOutOfReach
{
    public const int LIMIT = 50;

    /**
     * @param  class-string<Resource>  $resource
     * @param  class-string<Model>  $model
     * @param  list<int|string>  $keys
     * @param  list<string>  $paths
     */
    public function __construct(
        public string $resource,
        public string $model,
        public Endpoint $endpoint,
        public array $keys,
        public array $paths,
        public bool $capped,
        public string $guard,
        public int|string|null $user,
    ) {}

    /** @param array<string, int|string> $refused */
    public static function of(Resource $resource, array $refused, Endpoint $endpoint, ActingUser $acting): self
    {
        $keys = [];

        foreach ($refused as $key) {
            $keys[$key] ??= $key;
        }

        return new self(
            $resource::class,
            $resource::modelClass(),
            $endpoint,
            array_slice(array_values($keys), 0, self::LIMIT),
            array_slice(array_map(strval(...), array_keys($refused)), 0, self::LIMIT),
            count($keys) > self::LIMIT || count($refused) > self::LIMIT,
            $acting->guard,
            self::identifier($acting->user),
        );
    }

    private static function identifier(?Authenticatable $user): int|string|null
    {
        if ($user === null) {
            return null;
        }

        $identifier = $user->getAuthIdentifier();

        if ($identifier === null || is_int($identifier) || is_string($identifier)) {
            return $identifier;
        }

        if (is_scalar($identifier) || $identifier instanceof Stringable) {
            return (string) $identifier;
        }

        throw InvalidUserException::unusableIdentifier($user::class, $identifier);
    }
}
