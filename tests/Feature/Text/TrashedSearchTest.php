<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class TrashedSearchTest extends TextSearchTestCase
{
    private Author $lovelace;

    private Author $byron;

    private Author $hopper;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->lovelace = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Lovelace']);
        $this->byron = Author::factory()->create(['first_name' => 'Ada', 'last_name' => 'Byron']);
        $this->hopper = Author::factory()->create(['first_name' => 'Grace', 'last_name' => 'Hopper']);
        $this->byron->delete();
    }

    /**
     * @param  array<string, mixed>  $search
     * @param  list<string>  $authors
     */
    #[Test]
    #[DataProvider('trashedSearches')]
    public function it_adds_or_keeps_only_the_trashed_models(string $resource, array $search, array $authors): void
    {
        $response = $this->postJson($resource, ['search' => $search]);

        $response->assertOk()->assertJsonPath('data.*.id', array_map(fn (string $author): int => $this->{$author}->id, $authors));
    }

    /** @return array<string, array{string, array<string, mixed>, list<string>}> */
    public static function trashedSearches(): array
    {
        return [
            'without trashed' => ['/api/authors/search', [], ['hopper', 'lovelace']],
            'with trashed' => ['/api/authors/search', ['trashed' => 'with'], ['hopper', 'byron', 'lovelace']],
            'only trashed' => ['/api/authors/search', ['trashed' => 'only'], ['byron']],
            'a text without trashed' => ['/contains-authors/search', ['text' => ['value' => 'ada']], ['lovelace']],
            'a text with trashed' => ['/contains-authors/search', ['text' => ['value' => 'ada'], 'trashed' => 'with'], ['byron', 'lovelace']],
            'a text and only trashed' => ['/contains-authors/search', ['text' => ['value' => 'ada'], 'trashed' => 'only'], ['byron']],
            'a filter and only trashed' => ['/api/authors/search', ['trashed' => 'only', 'filters' => [['field' => 'first_name', 'value' => 'Grace']]], []],
        ];
    }
}
