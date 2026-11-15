<?php

namespace Lomkit\Rest\Fields;

enum FieldReduction: string
{
    case Hide = 'hide';
    case ReadOnly = 'readOnly';
    case Unfilterable = 'unfilterable';
    case Unsortable = 'unsortable';
}
