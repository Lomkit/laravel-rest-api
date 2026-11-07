<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Actions\ActionTarget;

class StandaloneExportDocuments extends ExportDocuments
{
    public ActionTarget $target = ActionTarget::None;
}
