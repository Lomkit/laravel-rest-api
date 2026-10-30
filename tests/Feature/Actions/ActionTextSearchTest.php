<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Lomkit\Rest\Tests\Fixtures\Workspace\Note;
use Lomkit\Rest\Tests\Fixtures\Workspace\Workspace;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ActionTextSearchTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Workspace];
    }

    /** @param array<string, mixed> $text */
    #[Test]
    #[DataProvider('texts')]
    public function it_refuses_a_text_search_on_an_action(array $text): void
    {
        Note::factory()->create(['title' => 'Roadmap']);
        Note::factory()->create(['title' => 'Budget']);

        $response = $this->postJson('/api/team-notes/actions/pin-notes', ['search' => ['text' => $text]]);

        $response->assertUnprocessable()->assertExactJson([
            'message' => 'Text search is not available for actions.',
            'errors' => ['search.text' => ['Text search is not available for actions.']],
        ]);
        $this->assertSame(0, Note::query()->where('pinned', true)->count());
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function texts(): array
    {
        return [
            'a text value' => [['value' => 'roadmap']],
            'a blank text value' => [['value' => '']],
        ];
    }

    #[Test]
    public function it_runs_an_action_on_the_search_without_text(): void
    {
        $roadmap = Note::factory()->create(['title' => 'Roadmap']);
        $budget = Note::factory()->create(['title' => 'Budget']);

        $response = $this->postJson('/api/team-notes/actions/pin-notes', [
            'search' => ['filters' => [['field' => 'title', 'value' => 'Roadmap']]],
        ]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
        $this->assertTrue($roadmap->fresh()?->pinned);
        $this->assertFalse($budget->fresh()?->pinned);
    }
}
