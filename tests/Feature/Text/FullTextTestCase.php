<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;

abstract class FullTextTestCase extends TextSearchTestCase
{
    /** @var list<string> */
    protected array $connectionsToTransact = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deleteRows();
        $this->beforeApplicationDestroyed(fn () => $this->deleteRows());
    }

    protected function requiresFullText(): void
    {
        if (! $this->runsFullText()) {
            $this->markTestSkipped('The database does not run full-text searches.');
        }
    }

    private function deleteRows(): void
    {
        Post::query()->delete();
        Author::withTrashed()->forceDelete();
    }
}
