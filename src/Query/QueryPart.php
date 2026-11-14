<?php

namespace Lomkit\Rest\Query;

enum QueryPart: string
{
    case Where = 'a where clause';
    case Join = 'a join';
    case Order = 'an order';
    case Limit = 'a limit';
    case Offset = 'an offset';
    case Columns = 'selected columns';
    case Distinct = 'distinct';
    case Lock = 'a lock';
    case Group = 'a group by';
    case Having = 'a having';
    case Union = 'a union';
    case EagerLoad = 'an eager load';
    case From = 'another table';
    case Callback = 'a before query callback';
    case RemovedScope = 'a removed global scope';
}
