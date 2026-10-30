<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Tests\Fixtures\Workspace\Note;
use Lomkit\Rest\Tests\Fixtures\Workspace\SqlOnlyTeamNoteResource;
use Lomkit\Rest\Tests\Fixtures\Workspace\Workspace;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TextSearchPerimeterTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Workspace];
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_keeps_a_text_search_inside_the_index_perimeter(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $roadmap = Note::factory()->create(['team' => 'red', 'title' => 'Roadmap']);
        Note::factory()->create(['team' => 'blue', 'title' => 'Roadmap']);
        Note::factory()->create(['team' => 'red', 'title' => 'Budget']);

        $response = $this->postJson('/api/team-notes/search', ['search' => ['text' => ['value' => 'roadmap']]]);

        $response->assertOk()->assertExactJson($this->page([$this->row($roadmap)], 1));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_keeps_a_search_without_text_inside_the_perimeter(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $roadmap = Note::factory()->create(['team' => 'red', 'title' => 'Roadmap']);
        Note::factory()->create(['team' => 'blue', 'title' => 'Roadmap']);
        $budget = Note::factory()->create(['team' => 'red', 'title' => 'Budget']);

        $response = $this->postJson('/api/team-notes/search');

        $response->assertOk()->assertExactJson($this->page([$this->row($budget), $this->row($roadmap)], 2));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_refuses_a_text_search_on_a_resource_without_index_perimeter(string $driver): void
    {
        config(['scout.driver' => $driver]);
        Note::factory()->create(['team' => 'red', 'title' => 'Roadmap']);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidResourceException::missingIndexPerimeter(SqlOnlyTeamNoteResource::class));

        $this->postJson('/api/sql-only-team-notes/search', ['search' => ['text' => ['value' => 'roadmap']]]);
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_runs_a_search_without_text_on_a_resource_without_index_perimeter(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $roadmap = Note::factory()->create(['team' => 'red', 'title' => 'Roadmap']);
        Note::factory()->create(['team' => 'blue', 'title' => 'Roadmap']);

        $withoutText = $this->postJson('/api/sql-only-team-notes/search');
        $withBlankText = $this->postJson('/api/sql-only-team-notes/search', ['search' => ['text' => ['value' => '']]]);

        $withoutText->assertOk()->assertExactJson($this->page([$this->row($roadmap)], 1));
        $withBlankText->assertOk()->assertExactJson($this->page([$this->row($roadmap)], 1));
    }

    #[Test]
    #[DataProvider('drivers')]
    public function it_applies_search_query_to_the_page_loaded_after_a_text_search(string $driver): void
    {
        config(['scout.driver' => $driver]);
        $first = Note::factory()->create(['team' => 'red', 'title' => 'Roadmap 2026']);
        Note::factory()->create(['team' => 'blue', 'title' => 'Roadmap 2027']);
        $third = Note::factory()->create(['team' => 'red', 'title' => 'Roadmap 2028']);

        $response = $this->postJson('/api/wide-index-team-notes/search', ['search' => ['text' => ['value' => 'roadmap']]]);

        $response->assertOk()->assertExactJson($this->page([$this->row($third), $this->row($first)], 3));
    }

    /** @return array<string, array{string}> */
    public static function drivers(): array
    {
        return [
            'collection' => ['collection'],
            'database' => ['database'],
        ];
    }

    /** @return array<string, bool|int|string> */
    private function row(Note $note): array
    {
        return ['id' => $note->id, 'team' => $note->team, 'title' => $note->title, 'pinned' => false];
    }

    /**
     * @param  list<array<string, bool|int|string>>  $rows
     * @return array<string, mixed>
     */
    private function page(array $rows, int $total): array
    {
        return [
            'current_page' => 1,
            'data' => $rows,
            'from' => 1,
            'last_page' => 1,
            'per_page' => 50,
            'to' => count($rows),
            'total' => $total,
            'meta' => [],
        ];
    }
}
