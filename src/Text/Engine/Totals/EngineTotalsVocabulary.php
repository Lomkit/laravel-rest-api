<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Validation\Search\TotalsVocabulary;

/** @internal */
final readonly class EngineTotalsVocabulary
{
    public const string UNTOTALLED = 'rest::validation.engine_totals';

    public static function of(EngineGrammar $engine): TotalsVocabulary
    {
        $totals = $engine->totals;

        if ($totals === null) {
            return TotalsVocabulary::refused(self::UNTOTALLED);
        }

        if (! $totals->declares()) {
            return TotalsVocabulary::refused(TotalsVocabulary::PROHIBITED);
        }

        $groups = [];
        $measures = [];

        foreach ($totals->groups as $name) {
            $groups[$name] = $engine->filters[$name]->field;
        }

        foreach (array_keys($totals->measures) as $name) {
            $measures[$name] = $engine->filters[$name]->field;
        }

        return TotalsVocabulary::of($groups, $measures, $totals->measures);
    }
}
