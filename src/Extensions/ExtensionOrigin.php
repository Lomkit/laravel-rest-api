<?php

namespace Lomkit\Rest\Extensions;

enum ExtensionOrigin: string
{
    case Config = 'config';
    case Runtime = 'runtime';
}
