<?php

namespace Lomkit\Rest\Tests\Feature\Deletion\Stubs;

use Lomkit\Rest\Tests\Fixtures\Archive\Document;

class AfterCommitDocumentObserver
{
    public bool $afterCommit = true;

    public function deleted(Document $document): void
    {
        DeletionTrace::$lines[] = 'committed deleted '.$document->getKey();
    }

    public function restored(Document $document): void
    {
        DeletionTrace::$lines[] = 'committed restored '.$document->getKey();
    }

    public function forceDeleted(Document $document): void
    {
        DeletionTrace::$lines[] = 'committed forceDeleted '.$document->getKey();
    }
}
