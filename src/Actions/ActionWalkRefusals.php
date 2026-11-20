<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Support\Translation;
use Lomkit\Rest\Text\Engine\EngineWalkRefusals;

/** @internal */
final readonly class ActionWalkRefusals implements EngineWalkRefusals
{
    #[\Override]
    public function beyondWindow(int $window): ValidationException
    {
        return self::refusal('rest::actions.engine_window', ['max' => (string) $window]);
    }

    #[\Override]
    public function changed(): ValidationException
    {
        return self::refusal('rest::actions.engine_results_changed');
    }

    public function unreported(): ValidationException
    {
        return self::refusal('rest::actions.engine_unreported');
    }

    /** @param array<string, string> $replace */
    private static function refusal(string $key, array $replace = []): ValidationException
    {
        return ValidationException::withMessages(['search' => [Translation::line($key, $replace)]]);
    }
}
