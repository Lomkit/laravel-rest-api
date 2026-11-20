<?php

namespace Lomkit\Rest\Text\Engine\Totals;

use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Validation\Search\TotalsVocabulary;

/** @internal */
final readonly class EngineTotalsVocabulary
{
    public const string UNTOTALLED = 'rest::validation.engine_totals';

    public const string UNCOMPUTED = 'rest::validation.engine_measure';

    public const string UNTOTALLED_TEXT = 'rest::validation.engine_text_totals';

    public static function of(EngineGrammar $engine, bool $listing): TotalsVocabulary
    {
        $totals = $engine->totals;

        if ($totals === null) {
            return TotalsVocabulary::refused(self::UNTOTALLED);
        }

        if (! $totals->declares()) {
            return TotalsVocabulary::refused(TotalsVocabulary::PROHIBITED);
        }

        if (! $listing && ! $totals->text) {
            return TotalsVocabulary::refused(self::UNTOTALLED_TEXT);
        }

        $groups = [];
        $measures = [];

        foreach ($totals->groups as $name) {
            $groups[$name] = $engine->filters[$name]->field;
        }

        foreach (array_keys($totals->measures) as $name) {
            $measures[$name] = $engine->filters[$name]->field;
        }

        return TotalsVocabulary::of($groups, $measures, $totals->measures, self::UNCOMPUTED, $totals->maxValues === null ? null : $totals->maxValues - 1);
    }
}
