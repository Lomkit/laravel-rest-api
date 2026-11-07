<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Lomkit\Rest\Contracts\BatchableAction;

class BatchedExportDocuments extends ExportDocuments implements BatchableAction {}
