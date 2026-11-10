<?php

namespace Lomkit\Rest\Actions\Rows;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Lomkit\Rest\Fields\ID;

use function Illuminate\Support\enum_value;

final readonly class CheckedRows implements ActionRows
{
    /** @param array<int|string, true> $keys */
    private function __construct(
        private ActionRows $rows,
        private ID $id,
        private array $keys,
    ) {}

    /** @param list<mixed> $checked */
    public static function of(ActionRows $rows, ID $id, array $checked, ?RowCap $cap): self
    {
        $keys = [];

        foreach ($checked as $value) {
            $key = self::key($id, $value);

            if ($key !== null) {
                $keys[$key] = true;
            }
        }

        $cap?->check(count($keys));

        return new self($rows, $id, $keys);
    }

    #[\Override]
    public function each(Closure $each): int
    {
        return $this->rows->each(fn (Collection $models): int => $each($models
            ->filter($this->checked(...))
            ->values()));
    }

    #[\Override]
    public function count(): int
    {
        return count($this->keys);
    }

    private function checked(Model $model): bool
    {
        $key = self::key($this->id, $model->getKey());

        return $key !== null && isset($this->keys[$key]);
    }

    private static function key(ID $id, mixed $value): int|string|null
    {
        $key = $id->parseKey(enum_value($value));

        return $key === null ? null : $id->normalizeKey($key);
    }
}
