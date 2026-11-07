<?php

namespace Lomkit\Rest\Metrics;

enum CacheScope: string
{
    case User = 'user';
    case Global = 'global';
}
