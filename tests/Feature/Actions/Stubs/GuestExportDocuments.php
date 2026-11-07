<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

class GuestExportDocuments extends ExportDocuments
{
    #[\Override]
    public function abilities(): array
    {
        return [];
    }
}
