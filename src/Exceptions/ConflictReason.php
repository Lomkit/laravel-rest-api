<?php

namespace Lomkit\Rest\Exceptions;

enum ConflictReason: string
{
    case Version = 'version';
    case Unlisted = 'unlisted';
}
