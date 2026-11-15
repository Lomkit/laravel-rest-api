<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Extensions\Contexts\IndexAttributesContext;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Text\Engine\StringListAttribute;

interface DeclaresIndexAttributes extends ExtensionKind
{
    /** @return list<Field|StringListAttribute> */
    public function indexAttributes(IndexAttributesContext $context): array;
}
