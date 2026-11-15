<?php

namespace Lomkit\Rest\Query;

/** @internal */
enum QueryPart: string
{
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
    case AfterCallback = 'an after query callback';
    case Scope = 'a global scope';
}
