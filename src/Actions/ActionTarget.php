<?php

namespace Lomkit\Rest\Actions;

enum ActionTarget: string
{
    case Search = 'search';
    case Resources = 'resources';
    case None = 'none';
}
