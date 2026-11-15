<?php

namespace Lomkit\Rest\Extensions\Kinds;

use Lomkit\Rest\Authorization\RowVersions;

interface VersionsRowChecks extends ExtensionKind
{
    public function versionRowChecks(RowVersions $versions): void;
}
