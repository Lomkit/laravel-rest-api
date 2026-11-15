<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Fields\Field;

interface DeclaresIndexAttributes extends ExtensionKind
{
    /** @return list<Field> */
    public function indexAttributes(IndexAttributesContext $context): array;
}
