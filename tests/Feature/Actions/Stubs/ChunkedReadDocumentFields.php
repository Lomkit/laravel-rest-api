<?php

namespace Lomkit\Rest\Tests\Feature\Actions\Stubs;

class ChunkedReadDocumentFields extends ReadDocumentFields
{
    public int $chunkCount = 2;
}
