<?php

namespace Lomkit\Rest\Extensions;

/** @internal */
enum ExtensionOrigin: string
{
    case Config = 'config';
    case Runtime = 'runtime';
}
