<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Authorization\Changes;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class CreatedTargetMovesTest extends WriteChecksTestCase
{
    private const string BOOK = 'Lomkit\\\\Rest\\\\Tests\\\\Fixtures\\\\Library\\\\Book';

    /** @var list<Changes> */
    private array $changes = [];

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Gate::before(function (?Authenticatable $user, string $ability, array $arguments): null {
            if ($ability === 'update' && ($arguments[1] ?? null) instanceof Changes) {
                $this->changes[] = $arguments[1];
            }

            return null;
        });
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('createdTargets')]
    public function it_checks_update_once_with_the_key_of_a_row_that_the_request_creates(string $uri, array $entry, string $column, string $update): void
    {
        $response = $this->mutate($uri, [$entry]);

        $response->assertOk();
        $this->assertSame([$update], array_values(array_filter(self::policies(), fn (string $line): bool => str_starts_with($line, 'policy update'))));
        $this->assertCount(1, $this->changes);
        $this->assertTrue($this->changes[0]->has($column));
        $this->assertNull($this->changes[0]->get($column));
        $this->assertFalse($this->changes[0]->target($column)?->exists);
        $this->assertSame('Walden', $this->changes[0]->target($column)->getAttribute('title'));
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('refusedMoves')]
    public function it_refuses_a_move_to_a_created_row_before_any_write(string $uri, array $entry, string $update, string $path): void
    {
        ChecksPolicy::$refused = [substr($update, strlen('policy '))];
        $rows = [self::rows('books'), self::rows('loans'), self::rows('labels')];

        $response = $this->mutate($uri, [$entry]);

        $response->assertForbidden();
        $response->assertJsonPath('denials.0.path', $path);
        $response->assertJsonPath('denials.0.ability', 'update');
        $response->assertJsonPath('denials.0.key', 102);
        $this->assertSame([], self::writes());
        $this->assertSame($rows, [self::rows('books'), self::rows('loans'), self::rows('labels')]);
    }

    #[Test]
    public function it_gives_the_stored_owner_as_the_target_of_a_key(): void
    {
        $response = $this->mutate('/api/loans/mutate', [['operation' => 'update', 'key' => 102, 'relations' => ['book' => ['operation' => 'attach', 'key' => 102]]]]);

        $response->assertOk();
        $this->assertCount(1, $this->changes);
        $this->assertSame(102, $this->changes[0]->get('book_id'));
        $this->assertTrue($this->changes[0]->target('book_id')?->exists);
        $this->assertSame('Emma', $this->changes[0]->target('book_id')->getAttribute('title'));
    }

    #[Test]
    public function it_gives_the_stored_parent_as_the_target_of_a_key(): void
    {
        $response = $this->mutate('/api/books/mutate', [['operation' => 'update', 'key' => 102, 'relations' => ['labels' => [['operation' => 'attach', 'key' => [102]]]]]]);

        $response->assertOk();
        $moved = array_values(array_filter($this->changes, fn (Changes $changes): bool => $changes->has('labelable_id')));
        $this->assertCount(1, $moved);
        $this->assertSame(102, $moved[0]->get('labelable_id'));
        $this->assertSame('Emma', $moved[0]->target('labelable_id')?->getAttribute('title'));
        $this->assertNull($moved[0]->target('labelable_type'));
    }

    #[Test]
    public function it_gives_no_target_to_a_detached_key(): void
    {
        $response = $this->mutate('/api/loans/mutate', [['operation' => 'update', 'key' => 101, 'relations' => ['book' => ['operation' => 'detach', 'key' => 101]]]]);

        $response->assertOk();
        $this->assertCount(1, $this->changes);
        $this->assertTrue($this->changes[0]->has('book_id'));
        $this->assertNull($this->changes[0]->target('book_id'));
    }

    /** @return array<string, array{string, array<string, mixed>, string, string}> */
    public static function createdTargets(): array
    {
        return array_map(fn (array $move): array => array_slice($move, 0, 4), self::moves());
    }

    /** @return array<string, array{string, array<string, mixed>, string, string}> */
    public static function refusedMoves(): array
    {
        return array_map(fn (array $move): array => [$move[0], $move[1], $move[3], $move[4]], self::moves());
    }

    /** @return array<string, array{string, array<string, mixed>, string, string, string}> */
    private static function moves(): array
    {
        $book = ['operation' => 'create', 'attributes' => ['title' => 'Walden']];

        return [
            'belongs to a created owner' => ['/api/loans/mutate', ['operation' => 'update', 'key' => 102, 'relations' => ['book' => $book]], 'book_id', 'policy update Loan(Grace) {"book_id":null}', 'mutate.0'],
            'has one under a created parent' => ['/api/books/mutate', [...$book, 'relations' => ['loan' => ['operation' => 'attach', 'key' => 102]]], 'book_id', 'policy update Loan(Grace) {"book_id":null}', 'mutate.0.relations.loan'],
            'morph many under a created parent' => ['/api/books/mutate', [...$book, 'relations' => ['labels' => [['operation' => 'attach', 'key' => [102]]]]], 'labelable_id', 'policy update Label(loose) {"labelable_id":null,"labelable_type":"'.self::BOOK.'"}', 'mutate.0.relations.labels.0'],
        ];
    }

    /** @return list<string> */
    private static function policies(): array
    {
        return array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => str_starts_with($line, 'policy ')));
    }
}
