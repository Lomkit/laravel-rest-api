<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

final class MaskingFieldsNamedB extends MaskingFields
{
    #[\Override]
    public static function name(): string
    {
        return 'b';
    }
}
