<?php

namespace Lomkit\Rest\Validation;

use Closure;
use Illuminate\Support\Fluent;
use Illuminate\Support\Str;
use Illuminate\Validation\ConditionalRules;
use Illuminate\Validation\Rules\ArrayRule;
use Illuminate\Validation\Rules\Date;
use Illuminate\Validation\Rules\Numeric;
use Illuminate\Validation\Rules\StringRule;
use Illuminate\Validation\Validator;
use ReflectionProperty;

final class RuleReferences
{
    private const EVERY_PARAMETER = [
        'Different', 'ExcludeWithout', 'MissingWith', 'MissingWithAll', 'PresentWith', 'PresentWithAll',
        'Prohibits', 'RequiredWith', 'RequiredWithAll', 'RequiredWithout', 'RequiredWithoutAll',
    ];

    private const NO_FIELD_PARAMETER = ['Prohibited', 'Unique'];

    private const READS_A_FIELD = ['DateEquals', 'InArray'];

    /** @var array<string, int>|null */
    private static ?array $dependentRules = null;

    /**
     * @param  list<mixed>  $rules
     * @param  list<string>  $indices
     * @return list<mixed>
     */
    public static function substituteWildcards(array $rules, array $indices): array
    {
        if ($indices === []) {
            return $rules;
        }

        return self::map($rules, fn (string $rule, int $position, string $parameter): string => self::isDependent($rule)
            ? self::fill($parameter, $indices)
            : $parameter);
    }

    /**
     * @param  list<mixed>  $rules
     * @param  array<string, string>  $paths
     * @return list<mixed>
     */
    public static function rewrite(array $rules, array $paths): array
    {
        if ($paths === []) {
            return $rules;
        }

        return self::map($rules, fn (string $rule, int $position, string $parameter): string => self::namesField($rule, $position)
            ? self::sibling($parameter, $paths) ?? $parameter
            : $parameter);
    }

    /**
     * @param  list<mixed>  $rules
     * @param  Closure(string, int, string): string  $replace
     * @return list<mixed>
     */
    private static function map(array $rules, Closure $replace): array
    {
        $mapped = [];

        foreach ($rules as $rule) {
            if ($rule instanceof ArrayRule || $rule instanceof Date || $rule instanceof Numeric || $rule instanceof StringRule) {
                array_push($mapped, ...array_map(fn (string $exploded): mixed => self::mapRule($exploded, $replace), explode('|', (string) $rule)));

                continue;
            }

            $mapped[] = $rule instanceof ConditionalRules ? self::mapConditional($rule, $replace) : self::mapRule($rule, $replace);
        }

        return $mapped;
    }

    /** @param Closure(string, int, string): string $replace */
    private static function mapConditional(ConditionalRules $rule, Closure $replace): ConditionalRules
    {
        return new ConditionalRules(
            fn (Fluent $data): bool => $rule->passes($data->getAttributes()),
            fn (Fluent $data): array => self::map(array_values($rule->rules($data->getAttributes())), $replace),
            fn (Fluent $data): array => self::map(array_values($rule->defaultRules($data->getAttributes())), $replace),
        );
    }

    /** @param Closure(string, int, string): string $replace */
    private static function mapRule(mixed $rule, Closure $replace): mixed
    {
        $head = self::head($rule);

        if ($head === null || ! self::readsAField($name = Str::studly(trim($head)))) {
            return $rule;
        }

        $parameters = self::parameters($rule);
        $mapped = [];

        foreach ($parameters as $position => $parameter) {
            $mapped[] = $replace($name, $position, $parameter);
        }

        if ($mapped === $parameters) {
            return $rule;
        }

        if (is_array($rule) || preg_grep('/[,"]/', $mapped) !== []) {
            return [$head, ...$mapped];
        }

        return $head.':'.implode(',', $mapped);
    }

    private static function head(mixed $rule): ?string
    {
        if (is_string($rule)) {
            $head = strstr($rule, ':', true);

            return $head === false ? null : $head;
        }

        if (is_array($rule) && array_is_list($rule) && is_string($rule[0] ?? null)) {
            return $rule[0];
        }

        return null;
    }

    /** @return list<string> */
    private static function parameters(mixed $rule): array
    {
        if (is_string($rule)) {
            return array_map(strval(...), str_getcsv(substr($rule, (int) strpos($rule, ':') + 1), escape: '\\'));
        }

        return array_values(array_map(fn (mixed $parameter): string => is_scalar($parameter) ? (string) $parameter : '', array_slice((array) $rule, 1)));
    }

    private static function namesField(string $rule, int $position): bool
    {
        if (in_array($rule, self::NO_FIELD_PARAMETER, true)) {
            return false;
        }

        return $position === 0 || in_array($rule, self::EVERY_PARAMETER, true);
    }

    /** @param array<string, string> $paths */
    private static function sibling(string $parameter, array $paths): ?string
    {
        if (isset($paths[$parameter])) {
            return $paths[$parameter];
        }

        $name = strstr($parameter, '.', true);

        return $name !== false && isset($paths[$name]) ? $paths[$name].substr($parameter, strlen($name)) : null;
    }

    /** @param list<string> $indices */
    private static function fill(string $parameter, array $indices): string
    {
        $parts = explode('*', $parameter);
        $filled = array_shift($parts);

        foreach ($parts as $position => $part) {
            $filled .= ($indices[$position] ?? '*').$part;
        }

        return $filled;
    }

    private static function readsAField(string $name): bool
    {
        return self::isDependent($name) || in_array($name, self::READS_A_FIELD, true);
    }

    private static function isDependent(string $name): bool
    {
        self::$dependentRules ??= array_flip((new ReflectionProperty(Validator::class, 'dependentRules'))->getDefaultValue());

        return isset(self::$dependentRules[$name]);
    }
}
