<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;

final readonly class LinkedRow
{
    private function __construct(
        public Model $model,
        public string $identity,
        private bool $draft,
    ) {}

    public static function stored(Model $row): self
    {
        return new self($row, LinkColumn::identity($row), false);
    }

    public static function draft(Model $model, int $number): self
    {
        return new self($model, 'draft#'.$number, true);
    }

    public function link(LinkColumn $column): ?string
    {
        return $this->draft ? null : $column->of($this->model);
    }

    public function owner(?string $keyName, ?string $type): string
    {
        if ($this->draft) {
            return $this->identity;
        }

        return (string) LinkColumn::owner($type, $this->model->getRawOriginal($keyName ?? $this->model->getKeyName()));
    }

    /** @return array{?string, mixed}|null */
    public function seed(?string $keyName, ?string $type): ?array
    {
        return $this->draft ? null : [$type, $this->model->getRawOriginal($keyName ?? $this->model->getKeyName())];
    }
}
