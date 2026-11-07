<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Lomkit\Rest\Contracts\BatchableAction;

class BatchedEncryptedExportDocuments extends ExportDocuments implements BatchableAction, ShouldBeEncrypted {}
