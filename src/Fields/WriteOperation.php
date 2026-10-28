<?php

namespace Lomkit\Rest\Fields;

enum WriteOperation: string
{
    case Create = 'create';
    case Update = 'update';
}
