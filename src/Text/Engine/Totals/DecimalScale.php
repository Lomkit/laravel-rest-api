<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Text\Engine\Profiles\ComputesTotals;
use Lomkit\Rest\Text\TextTarget;

/** @internal */
final readonly class DecimalScale
{
    public static function of(TextTarget $target, ComputesTotals $profile, Field $field): ?int
    {
        if (! $field instanceof Number || $field instanceof Integer) {
            return null;
        }

        $cast = $target->model->getCasts()[$field->name] ?? null;
        $scale = is_string($cast) && preg_match('/^decimal:(\d+)$/', $cast, $match) === 1 ? (int) $match[1] : null;

        if ($scale === null && $profile->approximatesDecimals()) {
            throw InvalidTextSearchException::unscaledMeasure($target->resource, $field->name, $target->model::class, $profile->name());
        }

        return $scale;
    }
}
