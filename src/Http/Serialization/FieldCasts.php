<?php

namespace Lomkit\Rest\Http\Serialization;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\FieldSet;

final readonly class FieldCasts
{
    /** @param array<string, string> $casts */
    private function __construct(private array $casts) {}

    /** @param list<string> $names */
    public static function of(FieldSet $fields, array $names): self
    {
        $casts = [];

        foreach ($names as $name) {
            $cast = $fields->get($name)->modelCast();

            if ($cast !== null) {
                $casts[$name] = $cast;
            }
        }

        return new self($casts);
    }

    public function missingOn(Model $model): self
    {
        if ($this->casts === []) {
            return $this;
        }

        $dates = $model->getDates();

        return new self(array_filter(
            $this->casts,
            static fn (string $name): bool => ! $model->hasCast($name) && ! in_array($name, $dates, true),
            ARRAY_FILTER_USE_KEY,
        ));
    }

    /**
     * @template TModel of Model
     *
     * @param  TModel  $copy
     * @return TModel
     */
    public function appliedTo(Model $copy): Model
    {
        if ($this->casts === []) {
            return $copy;
        }

        return $copy->mergeCasts($this->casts);
    }
}
