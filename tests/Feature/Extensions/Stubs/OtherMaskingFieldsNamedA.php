<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class OtherMaskingFieldsNamedA extends OtherMaskingFields
{
    #[\Override]
    public static function name(): string
    {
        return 'a';
    }
}
