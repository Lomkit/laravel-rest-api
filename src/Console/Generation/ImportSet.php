<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Fields\Boolean;
use Lomkit\Rest\Fields\Date;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Relations\BelongsToMany;
use Lomkit\Rest\Relations\HasMany;
use Lomkit\Rest\Relations\HasManyThrough;
use Lomkit\Rest\Relations\HasOne;
use Lomkit\Rest\Relations\HasOneThrough;
use Lomkit\Rest\Relations\MorphedByMany;
use Lomkit\Rest\Relations\MorphMany;
use Lomkit\Rest\Relations\MorphOne;
use Lomkit\Rest\Relations\MorphToMany;

final readonly class ImportSet
{
    private const array RESOURCE_CLASSES = [
        RestRequest::class,
        Rule::class,
        Boolean::class,
        Date::class,
        DateTime::class,
        Enum::class,
        ID::class,
        Integer::class,
        Json::class,
        Number::class,
        Text::class,
        BelongsTo::class,
        BelongsToMany::class,
        HasMany::class,
        HasManyThrough::class,
        HasOne::class,
        HasOneThrough::class,
        MorphedByMany::class,
        MorphMany::class,
        MorphOne::class,
        MorphToMany::class,
    ];

    /**
     * @param  array<string, string>  $owners
     * @param  array<string, string>  $names
     */
    private function __construct(private string $class, private array $owners, private array $names) {}

    public static function forResource(string $class, string $baseClass): self
    {
        return self::reserving($class, [$baseClass, ...self::RESOURCE_CLASSES]);
    }

    public static function forController(string $class, string $baseClass): self
    {
        return self::reserving($class, [$baseClass]);
    }

    public static function forPolicy(string $class): self
    {
        return self::reserving($class, []);
    }

    public function with(string $class, string $aliasSuffix): self
    {
        if (isset($this->names[$class])) {
            return $this;
        }

        $name = class_basename($class);

        while (($this->owners[strtolower($name)] ?? $class) !== $class) {
            $name .= $aliasSuffix;
        }

        return new self(
            $this->class,
            [...$this->owners, strtolower($name) => $class],
            [...$this->names, $class => $name],
        );
    }

    public function name(string $class): string
    {
        return $this->names[$class] ?? class_basename($class);
    }

    public function statement(string $class): ?string
    {
        $name = $this->name($class);

        if ($name !== class_basename($class)) {
            return "{$class} as {$name}";
        }

        return Str::beforeLast($class, '\\') === Str::beforeLast($this->class, '\\') ? null : $class;
    }

    /** @param  list<string>  $reserved */
    private static function reserving(string $class, array $reserved): self
    {
        $owners = [];

        foreach ([...$reserved, $class] as $owner) {
            $owners[strtolower(class_basename($owner))] = $owner;
        }

        return new self($class, $owners, []);
    }
}
