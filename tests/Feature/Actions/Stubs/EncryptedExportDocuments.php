<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

use Illuminate\Contracts\Queue\ShouldBeEncrypted;

class EncryptedExportDocuments extends ExportDocuments implements ShouldBeEncrypted {}
