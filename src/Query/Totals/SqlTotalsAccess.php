<?php

namespace Lomkit\Rest\Query\Totals;

use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Support\Overrides;
use Lomkit\Rest\Validation\Search\TotalsVocabulary;

/** @internal */
final readonly class SqlTotalsAccess
{
    public const string CAPPED = 'rest::validation.capped_totals';

    public const string CUSTOM_PAGE = 'rest::validation.custom_page_totals';

    public static function vocabulary(Resource $resource): TotalsVocabulary
    {
        $refusal = self::refusal($resource);

        if ($refusal !== null) {
            return TotalsVocabulary::refused($refusal);
        }

        $fields = $resource->definition()->fields();

        return TotalsVocabulary::of(TotalsVocabulary::named($fields->groupable()), TotalsVocabulary::named($fields->measurable()));
    }

    public static function documented(Resource $resource): bool
    {
        $declared = $resource->definition()->declaredFields();

        return (count($declared->groupable()) > 0 || count($declared->measurable()) > 0)
            && $resource->definition()->declaredLimits()->maxTotal === null
            && ! Overrides::method($resource, 'paginate', Resource::class);
    }

    public static function allows(Resource $resource): bool
    {
        return self::refusal($resource) === null;
    }

    private static function refusal(Resource $resource): ?string
    {
        $declared = $resource->definition()->declaredFields();

        return match (true) {
            count($declared->groupable()) === 0 && count($declared->measurable()) === 0 => TotalsVocabulary::PROHIBITED,
            $resource->definition()->limits()->maxTotal !== null => self::CAPPED,
            Overrides::method($resource, 'paginate', Resource::class) => self::CUSTOM_PAGE,
            default => null,
        };
    }
}
