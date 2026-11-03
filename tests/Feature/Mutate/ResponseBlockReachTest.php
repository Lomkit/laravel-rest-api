<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResponseBlockReachTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[Test]
    public function it_returns_null_for_a_record_outside_the_search_query_of_its_resource(): void
    {
        $minutes = Document::query()->create(['title' => 'Minutes', 'archived' => true]);

        $this->postJson('/api/current-documents/mutate', [
            'mutate' => [
                ['operation' => 'update', 'key' => $minutes->id, 'attributes' => ['title' => 'Old minutes']],
                ['operation' => 'create', 'attributes' => ['title' => 'Agenda']],
            ],
            'response' => ['selects' => [['field' => 'title']]],
        ])
            ->assertOk()
            ->assertJsonPath('records', [null, ['title' => 'Agenda']]);

        $this->assertSame('Old minutes', $minutes->refresh()->title);
    }
}
