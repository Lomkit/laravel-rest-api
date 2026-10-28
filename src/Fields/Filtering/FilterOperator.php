<?php

namespace Lomkit\Rest\Fields\Filtering;

use Lomkit\Rest\Exceptions\InvalidFieldException;

enum FilterOperator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case GreaterThan = '>';
    case GreaterThanOrEqual = '>=';
    case LessThan = '<';
    case LessThanOrEqual = '<=';
    case Like = 'like';
    case NotLike = 'not like';
    case ILike = 'ilike';
    case NotILike = 'not ilike';
    case In = 'in';
    case NotIn = 'not in';
    case Between = 'between';
    case NotBetween = 'not between';
    case IsNull = 'is null';
    case IsNotNull = 'is not null';

    public static function parse(self|string $operator): self
    {
        if ($operator instanceof self) {
            return $operator;
        }

        return self::tryFrom(strtolower($operator)) ?? throw InvalidFieldException::unknownOperator($operator);
    }

    public function condition(): Condition
    {
        return match ($this) {
            self::Equal, self::NotEqual, self::GreaterThan, self::GreaterThanOrEqual, self::LessThan, self::LessThanOrEqual => new Comparison($this->value),
            self::Like => new Pattern(false),
            self::NotLike => new Pattern(true),
            self::ILike => new CaseInsensitivePattern(false),
            self::NotILike => new CaseInsensitivePattern(true),
            self::In => new Membership(false),
            self::NotIn => new Membership(true),
            self::Between => new Range(false),
            self::NotBetween => new Range(true),
            self::IsNull => new Nullity(false),
            self::IsNotNull => new Nullity(true),
        };
    }
}
