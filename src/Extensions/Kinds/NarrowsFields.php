<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\FieldsContext;

interface NarrowsFields extends ExtensionKind
{
    public function narrowFields(FieldsContext $context): void;
}
