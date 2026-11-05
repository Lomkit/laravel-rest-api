<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Illuminate\Database\Eloquent\Builder;
use LogicException;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Validation\KeyLookup;
use Lomkit\Rest\Validation\KeyRead;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class KeyLookupTest extends PackageTestCase
{
    use CapturesQueries;

    private const string DRAFT = '019a35f0-7c2e-7a41-9b3d-5e8f1c2a4b60';

    private const string UPPER_DRAFT = '019A35F0-7C2E-7A41-9B3D-5E8F1C2A4B60';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive, new Publishing];
    }

    #[Test]
    public function it_loads_the_rows_of_the_keys_it_finds_with_the_same_query(): void
    {
        Document::factory()->create(['id' => 1, 'title' => 'Annual report']);
        Document::factory()->create(['id' => 2, 'title' => 'Quarterly report']);
        $lookup = new KeyLookup(fn (): Builder => Document::query(), ID::make('id'), KeyRead::Rows);
        $lookup->expectAll([2, 3]);
        $this->captureQueries();

        $rows = $lookup->rows();

        $this->assertSame([2], array_map(fn (Document $row): mixed => $row->getKey(), $rows));
        $this->assertSame('Quarterly report', $rows[0]->getAttribute('title'));
        $this->assertSame([true, false], [$lookup->has(2), $lookup->has(3)]);
        $this->assertCount(1, $this->statements);
        $this->assertStringStartsWith('select * from documents where documents.id in (2, 3)', $this->statements[0][0]);
    }

    #[Test]
    public function it_refuses_to_give_rows_when_it_checks_the_keys_only(): void
    {
        $lookup = new KeyLookup(fn (): Builder => Document::query(), ID::make('id'));

        $this->expectExceptionObject(new LogicException('The key lookup checks the keys without loading their rows.'));

        $lookup->rows();
    }

    #[Test]
    public function it_checks_keys_in_chunks_of_one_thousand(): void
    {
        Document::factory()->create(['id' => 1, 'title' => 'Annual report']);
        Document::factory()->create(['id' => 2, 'title' => 'Quarterly report']);
        Document::factory()->create(['id' => 3, 'title' => 'Monthly report']);
        $lookup = new KeyLookup(fn (): Builder => Document::query(), ID::make('id'));

        foreach (range(1, 2500) as $key) {
            $lookup->expect($key);
        }

        $this->captureQueries();

        $this->assertTrue($lookup->has(1));
        $this->assertTrue($lookup->has(3));
        $this->assertFalse($lookup->has(4));
        $this->assertFalse($lookup->has(2500));

        $queries = $this->keyQueries('documents');
        $this->assertCount(3, $this->statements);
        $this->assertCount(3, $queries);
        $this->assertSame([[], [], []], array_column($queries, 1));
        $this->assertStringContainsString('in (1, 2, 3, ', $queries[0][0]);
        $this->assertStringContainsString(', 2500)', $queries[2][0]);
        $this->assertStringNotContainsString('2501', $queries[2][0]);

        $this->assertTrue($lookup->has(2));
        $this->assertCount(3, $this->statements);
    }

    #[Test]
    public function it_checks_the_keys_within_the_query_it_is_given(): void
    {
        Document::factory()->create(['id' => 1, 'title' => 'Annual report', 'archived' => false]);
        Document::factory()->create(['id' => 2, 'title' => 'Old ledger', 'archived' => true]);
        $lookup = new KeyLookup(fn (): Builder => Document::query()->where('archived', false), ID::make('id'));

        $lookup->expect(1);
        $lookup->expect(2);

        $this->assertTrue($lookup->has(1));
        $this->assertFalse($lookup->has(2));
    }

    #[Test]
    public function it_finds_a_uuid_key_whenever_the_database_matches_it(): void
    {
        $key = '019A35F0-7C2E-7A41-9B3D-5E8F1C2A4B60';
        Draft::factory()
            ->for(Author::factory())
            ->create(['id' => '019a35f0-7c2e-7a41-9b3d-5e8f1c2a4b60']);
        $lookup = new KeyLookup(fn (): Builder => Draft::query(), ID::make('id')->keyType('uuid'));
        $lookup->expect($key);
        $this->captureQueries();

        $found = $lookup->has($key);

        $this->assertCount(1, $this->statements);
        $this->assertSame([$key], $this->keyQueries('drafts')[0][1]);
        $this->assertSame(
            Draft::query()
                ->whereKey($key)
                ->exists(),
            $found,
        );
    }

    #[Test]
    #[DataProvider('caseVariants')]
    public function it_checks_each_case_of_a_uuid_key_as_it_was_sent(string $first, string $second): void
    {
        Draft::factory()
            ->for(Author::factory())
            ->create(['id' => self::DRAFT]);
        $lookup = new KeyLookup(fn (): Builder => Draft::query(), ID::make('id')->keyType('uuid'));
        $lookup->expect($first);
        $lookup->expectAll([$second]);
        $this->captureQueries();

        $found = [$lookup->has(self::DRAFT), $lookup->has(self::UPPER_DRAFT)];

        $this->assertSame([[$first], [$second]], array_column($this->keyQueries('drafts'), 1));
        $this->assertCount(2, $this->statements);
        $this->assertSame(
            [
                true,
                Draft::query()
                    ->whereKey(self::UPPER_DRAFT)
                    ->exists(),
            ],
            $found,
        );
    }

    /** @return array<string, array{string, string}> */
    public static function caseVariants(): array
    {
        return [
            'the lowercase form first' => [self::DRAFT, self::UPPER_DRAFT],
            'the uppercase form first' => [self::UPPER_DRAFT, self::DRAFT],
        ];
    }

    #[Test]
    public function it_builds_the_key_query_once_for_every_case_of_a_key(): void
    {
        $built = 0;
        Draft::factory()
            ->for(Author::factory())
            ->create(['id' => self::DRAFT]);
        $lookup = new KeyLookup(function () use (&$built): Builder {
            $built++;

            return Draft::query();
        }, ID::make('id')->keyType('uuid'));
        $lookup->expect(self::DRAFT);
        $lookup->expect(self::UPPER_DRAFT);
        $this->captureQueries();

        $lookup->has(self::DRAFT);

        $this->assertCount(2, $this->statements);
        $this->assertSame(1, $built);
    }
}
