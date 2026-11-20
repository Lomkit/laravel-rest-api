<?php

namespace Lomkit\Rest\Text\Engine\Profiles;

use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\Number;
use Lomkit\Rest\Search\Totals\MeasureType;

/** @internal */
trait MeasuresNumbers
{
    /** @return list<MeasureType> */
    #[\Override]
    public function measureTypes(Field $attribute): array
    {
        return $attribute instanceof Number ? MeasureType::cases() : [];
    }
}
