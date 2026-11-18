<?php

namespace Lomkit\Rest\Exceptions;

use Illuminate\Validation\ValidationException;
use LogicException;
use Lomkit\Rest\Support\Translation;
use Lomkit\Rest\Text\Engine\RestrictingExtension;

final class ConflictingListsException extends LogicException
{
    private function __construct(string $message, private readonly string $attribute)
    {
        parent::__construct($message);
    }

    public static function memberships(string $resource, string $attribute, ?RestrictingExtension $first, ?RestrictingExtension $second, string $engine): self
    {
        $sources = $first === null && $second === null ? 'ScoutEngine::perimeter() or instructions' : self::source($first).' and '.self::source($second);

        return new self("The engine query of {$resource} keeps the rows whose list attribute [{$attribute}] shares a value with each of two lists, set by {$sources}, and neither list holds the other. The engine [{$engine}] cannot AND two lists on one attribute: restrict [{$attribute}] with one list, or with lists that hold one another, or search on an engine that ANDs them (the built-in Typesense, Meilisearch and Turbopuffer profiles).", $attribute);
    }

    public function refusal(string $key): ValidationException
    {
        return ValidationException::withMessages([$key => [Translation::line('rest::validation.engine_list_conflict', ['attribute' => $key, 'list' => $this->attribute])]]);
    }

    private static function source(?RestrictingExtension $source): string
    {
        return $source === null ? 'ScoutEngine::perimeter() or an instruction' : "the [{$source->name}] extension {$source->class}";
    }
}
