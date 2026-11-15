<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Authorization\PendingChecks;

interface PreparesRowChecks extends ExtensionKind
{
    public function prepareRowChecks(PendingChecks $checks): void;
}
