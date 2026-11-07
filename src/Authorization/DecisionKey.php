<?php

namespace Lomkit\Rest\Authorization;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

final readonly class DecisionKey
{
    private const string PREFIX = 'rest.authorizations.';

    /** @param list<string> $parts */
    private function __construct(private array $parts) {}

    public static function of(Check $check, string $context, int $since): ?self
    {
        $parts = [$check->key()];

        foreach ($check->models() as $model) {
            $version = self::version($model, $since);

            if ($version === null) {
                return null;
            }

            $parts[] = $version;
        }

        return new self([...$parts, $context]);
    }

    public function for(UserIdentity $user, string $stamps): string
    {
        return self::PREFIX.hash('sha256', implode('|', [$stamps, $user->key(), ...$this->parts]));
    }

    private static function version(Model $model, int $since): ?string
    {
        $column = $model->getUpdatedAtColumn();

        if ($column === null || ! $model->usesTimestamps() || ! array_key_exists($column, $model->getAttributes())) {
            return null;
        }

        $updatedAt = $model->getAttribute($column);

        if (! $updatedAt instanceof DateTimeInterface || $updatedAt->getTimestamp() >= $since) {
            return null;
        }

        return $updatedAt->format('U.u').':'.self::loaded($model);
    }

    private static function loaded(Model $model): string
    {
        $names = array_map(rawurlencode(...), array_keys($model->getAttributes()));
        sort($names, SORT_STRING);

        return implode(',', $names);
    }
}
