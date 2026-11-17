<?php

namespace Lomkit\Rest\Text\Engine;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Filtering\FilterOperator;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Text\Engine\Profiles\EngineProfile;

final readonly class IndexAttributeRules
{
    private const string NAME = '/\A[A-Za-z_][A-Za-z0-9_]*\z/';

    private const string SOFT_DELETED = '__soft_deleted';

    private const array TYPES = [ID::class, Integer::class, Number::class, Boolean::class, Text::class, Enum::class];

    public static function field(Field $field, EngineProfile $profile, IndexDeclarer $declarer): IndexAttribute
    {
        self::ensureName($field->name, $declarer);

        if (! in_array($field::class, self::TYPES, true)) {
            throw $declarer->unsupportedType($field->name, $field::class);
        }

        if ($field->filtersThroughCallback() || $field->sortsThroughCallback()) {
            throw $declarer->callback($field->name);
        }

        if ($field instanceof Enum) {
            $field->enumClass();
        }

        $supported = $profile->operators($field);
        $operators = array_filter($field->filterOperators(), fn (FilterOperator $operator): bool => in_array($operator, $supported, true));

        return $operators === []
            ? throw $declarer->unfilterable($field->name)
            : new IndexAttribute($field, array_values($operators), $profile->literal());
    }

    public static function stringList(StringListAttribute $attribute, Model $model, EngineProfile $profile, IndexDeclarer $declarer): IndexAttribute
    {
        self::ensureName($attribute->name, $declarer);

        $field = Text::make($attribute->name)->boundTo($model);
        $supported = $profile->operators($field);
        $operators = array_filter([FilterOperator::In, FilterOperator::NotIn], fn (FilterOperator $operator): bool => in_array($operator, $supported, true));

        return $operators === []
            ? throw $declarer->unfilterable($attribute->name)
            : new IndexAttribute($field, array_values($operators), $profile->literal(), list: true);
    }

    public static function ensureName(string $name, IndexDeclarer $declarer): void
    {
        if (preg_match(self::NAME, $name) !== 1 || $name === self::SOFT_DELETED) {
            throw $declarer->invalidName($name);
        }
    }
}
