<?php

namespace Lomkit\Rest\Text\Engine;

/** @internal */
enum ResourceIndexList: string
{
    case Filterable = 'filterable';
    case Perimeter = 'perimeter';
    case IndexAttributes = 'indexAttributes';
}
