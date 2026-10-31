<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Support\Arr;
use InvalidArgumentException;

final class InvalidModelTableException extends InvalidArgumentException
{
    public static function noModel(): self
    {
        return new self('No model was given or found for this resource. Pass --model to generate its fields.');
    }

    public static function missingModel(string $class): self
    {
        return new self("The model [{$class}] does not exist.");
    }

    public static function notAModel(string $class): self
    {
        return new self("The class [{$class}] is not an Eloquent model.");
    }

    public static function notInstantiable(string $class): self
    {
        return new self("The model [{$class}] cannot be instantiated. Pass a concrete model.");
    }

    public static function missingTable(string $table, string $class): self
    {
        return new self("The table [{$table}] of the model [{$class}] does not exist. Run the migrations, then generate the resource again.");
    }

    /** @param  list<string>  $classes */
    public static function sharedResourceName(string $name, array $classes): self
    {
        $models = Arr::join(array_map(fn (string $class): string => "[{$class}]", $classes), ', ', ' and ');

        return new self("The models {$models} would share the resource name [{$name}], so their routes would have the same URIs and names. Expose them in separate runs of this command, or rename one of the models.");
    }
}
