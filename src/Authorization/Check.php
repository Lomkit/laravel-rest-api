<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Resource;
use Stringable;

use function Illuminate\Support\enum_value;

final readonly class Check
{
    /** @param list<Model> $related */
    public function __construct(
        public Resource $resource,
        public string $ability,
        public Model|string $subject,
        public string $path = '',
        public array $related = [],
    ) {}

    public static function onClass(Resource $resource, string $ability, string $path = ''): self
    {
        return new self($resource, $ability, $resource::modelClass(), $path);
    }

    /** @return list<string> */
    public static function rowsOf(Model $model): array
    {
        return array_values(array_unique([self::rowOf($model), self::instanceKey($model)]));
    }

    /** @return list<Model|string> */
    public function arguments(): array
    {
        return [$this->subject, ...$this->related];
    }

    /** @return list<Model> */
    public function models(): array
    {
        return array_values(array_filter($this->arguments(), fn (Model|string $argument): bool => $argument instanceof Model));
    }

    /** @return list<string> */
    public function rows(): array
    {
        return array_map(self::rowOf(...), $this->models());
    }

    public function isKeyedByInstance(): bool
    {
        foreach ($this->models() as $model) {
            if (self::storedKey($model) === null) {
                return true;
            }
        }

        return false;
    }

    public function key(): string
    {
        return implode('|', [
            $this->resource::class,
            $this->ability,
            ...array_map(self::argumentKey(...), $this->arguments()),
        ]);
    }

    private static function argumentKey(Model|string $argument): string
    {
        if (is_string($argument)) {
            return $argument;
        }

        return $argument::class.'#'.(self::storedKey($argument) ?? self::instanceKey($argument));
    }

    private static function rowOf(Model $model): string
    {
        $key = self::storedKey($model);

        return $key === null ? self::instanceKey($model) : $model->getTable().'#'.$key;
    }

    private static function instanceKey(Model $model): string
    {
        return 'instance:'.spl_object_id($model);
    }

    private static function storedKey(Model $model): ?string
    {
        $key = enum_value($model->getKey());

        if (! $model->exists || ! (is_int($key) || is_string($key) || $key instanceof Stringable)) {
            return null;
        }

        return rawurlencode((string) $key);
    }
}
