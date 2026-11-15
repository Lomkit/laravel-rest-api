<?php

namespace Lomkit\Rest\Fields;

/** @internal */
enum FieldReduction: string
{
    case Hide = 'hide';
    case ReadOnly = 'readOnly';
    case Unfilterable = 'unfilterable';
    case Unsortable = 'unsortable';
}
