<?php

namespace Lomkit\Rest\Tests\Feature\MorphTargets;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\MorphTargets\Stubs\TargetLabelPolicy;
use Lomkit\Rest\Tests\Feature\Search\Stubs\BookLabelController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphToMutateTypesTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Relation::morphMap(['book' => Book::class, 'shelf' => Shelf::class], false);
        Gate::policy(Label::class, TargetLabelPolicy::class);
        TargetLabelPolicy::$calls = [];
        TargetLabelPolicy::$denied = [];
        Book::query()->create(['id' => 101, 'title' => 'Dune']);
        Book::query()->create(['id' => 102, 'title' => 'Emma']);
        Shelf::query()->create(['id' => 101, 'name' => 'Classics']);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Relation::morphMap([], false);

        parent::tearDown();
    }

    #[Test]
    #[DataProvider('targetTypes')]
    public function it_attaches_the_target_of_the_type_that_the_entry_names(string $type, string $ability): void
    {
        $response = $this->postJson('/api/labels/mutate', ['mutate' => [[
            'operation' => 'create',
            'attributes' => ['text' => 'Classic'],
            'relations' => ['labelable' => ['operation' => 'attach', 'type' => $type, 'key' => 101]],
        ]]]);

        $label = Label::query()->sole();
        $response->assertOk()->assertExactJson(['operations' => [['operation' => 'create', 'key' => $label->id, 'relations' => ['labelable' => ['operation' => 'attach', 'type' => $type, 'key' => 101]]]]]);
        $this->assertSame([$type, 101], [$label->labelable_type, $label->labelable_id]);
        $this->assertSame([$ability.' 101'], TargetLabelPolicy::$calls);
    }

    /** @return array<string, array{string, string}> */
    public static function targetTypes(): array
    {
        return ['a book' => ['book', 'attachBook'], 'a shelf' => ['shelf', 'attachShelf']];
    }

    #[Test]
    public function it_writes_the_target_of_its_type_on_update_create_and_detach(): void
    {
        $first = Label::query()->create(['text' => 'First', 'labelable_type' => 'book', 'labelable_id' => 101]);
        $second = Label::query()->create(['text' => 'Second', 'labelable_type' => 'book', 'labelable_id' => 102]);
        $third = Label::query()->create(['text' => 'Third', 'labelable_type' => 'shelf', 'labelable_id' => 101]);

        $response = $this->postJson('/api/labels/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $first->id, 'relations' => ['labelable' => ['operation' => 'update', 'type' => 'shelf', 'key' => 101, 'attributes' => ['name' => 'Rare']]]],
            ['operation' => 'update', 'key' => $second->id, 'relations' => ['labelable' => ['operation' => 'create', 'type' => 'shelf', 'attributes' => ['name' => 'Poetry']]]],
            ['operation' => 'update', 'key' => $third->id, 'relations' => ['labelable' => ['operation' => 'detach', 'type' => 'shelf', 'key' => 101]]],
        ]]);

        $poetry = Shelf::query()
            ->where('name', 'Poetry')
            ->sole();
        $response->assertOk()->assertJsonPath('operations.1.relations.labelable', ['operation' => 'create', 'type' => 'shelf', 'key' => $poetry->id]);
        $this->assertSame(['Rare', 'Dune'], [Shelf::query()->find(101)?->name, Book::query()->find(101)?->title]);
        $this->assertSame([['shelf', 101], ['shelf', $poetry->id], [null, null]], array_map(
            fn (Label $label): array => [$label->fresh()?->labelable_type, $label->fresh()?->labelable_id],
            [$first, $second, $third],
        ));
        $this->assertSame(['attachShelf 101', 'attachShelf '.$poetry->id, 'detachShelf 101'], TargetLabelPolicy::$calls);
    }

    /** @param array<string, mixed> $entry */
    #[Test]
    #[DataProvider('deniedEntries')]
    public function it_asks_the_policy_of_the_parent_for_the_type_of_the_target(array $entry, string $denied): void
    {
        TargetLabelPolicy::$denied = [$denied];
        $label = Label::query()->create(['text' => 'Classic', 'labelable_type' => 'shelf', 'labelable_id' => 101]);

        $response = $this->postJson('/api/labels/mutate', ['mutate' => [['operation' => 'update', 'key' => $label->id, 'relations' => ['labelable' => $entry]]]]);

        $response->assertForbidden()->assertJsonPath('message', 'This target is locked.');
        $this->assertSame(['shelf', 101, 'Classics'], [$label->fresh()?->labelable_type, $label->fresh()?->labelable_id, Shelf::query()->find(101)?->name]);
        $this->assertSame([$denied.' 101'], TargetLabelPolicy::$calls);
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function deniedEntries(): array
    {
        return [
            'an attach' => [['operation' => 'attach', 'type' => 'shelf', 'key' => 101], 'attachShelf'],
            'an update' => [['operation' => 'update', 'type' => 'shelf', 'key' => 101, 'attributes' => ['name' => 'Rare']], 'attachShelf'],
            'a detach' => [['operation' => 'detach', 'type' => 'shelf', 'key' => 101], 'detachShelf'],
        ];
    }

    /**
     * @param  array<string, mixed>  $entry
     * @param  array<string, list<string>>  $errors
     * @param  list<string>  $queries
     */
    #[Test]
    #[DataProvider('refusedEntries')]
    public function it_refuses_an_entry_that_names_no_declared_type(array $entry, array $errors, array $queries = []): void
    {
        DB::enableQueryLog();

        $response = $this->postJson('/api/labels/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['text' => 'Classic'], 'relations' => ['labelable' => $entry]]]]);

        $this->assertExactErrors($response, $errors);
        $this->assertSame($queries, array_map(fn (array $query): string => str_replace(['"', '`'], '', $query['query']), DB::getQueryLog()));
        $this->assertSame(0, Label::query()->count());
    }

    /** @return array<string, array{0: array<string, mixed>, 1: array<string, list<string>>, 2?: list<string>}> */
    public static function refusedEntries(): array
    {
        $type = 'mutate.0.relations.labelable.type';
        $invalid = [$type => ['The selected '.$type.' is invalid.']];

        return [
            'no type' => [['operation' => 'attach', 'key' => 101], [$type => ['The '.$type.' field is required.']]],
            'a type that the relation does not declare' => [['operation' => 'attach', 'type' => 'comment', 'key' => 101], $invalid],
            'the class of a mapped model' => [['operation' => 'attach', 'type' => Book::class, 'key' => 101], $invalid],
            'a key that only another type holds' => [['operation' => 'attach', 'type' => 'shelf', 'key' => 102], ['mutate.0.relations.labelable.key' => ['The selected mutate.0.relations.labelable.key is invalid.']], ['select * from shelves where shelves.id in (102)']],
        ];
    }

    #[Test]
    public function it_takes_the_one_type_of_a_single_target_when_the_entry_names_none(): void
    {
        Rest::resource('book-labels', BookLabelController::class)->register();
        $entry = ['operation' => 'attach', 'key' => 101];

        $none = $this->postJson('/book-labels/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['text' => 'None'], 'relations' => ['labelable' => $entry]]]]);
        $book = $this->postJson('/book-labels/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['text' => 'Book'], 'relations' => ['labelable' => ['type' => 'book', ...$entry]]]]]);
        $shelf = $this->postJson('/book-labels/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['text' => 'Shelf'], 'relations' => ['labelable' => ['type' => 'shelf', ...$entry]]]]]);

        $none->assertOk()->assertJsonPath('operations.0.relations.labelable.type', 'book');
        $book->assertOk();
        $this->assertExactErrors($shelf, ['mutate.0.relations.labelable.type' => ['The selected mutate.0.relations.labelable.type is invalid.']]);
        $this->assertSame(['None', 'Book'], Label::query()
            ->orderBy('id')
            ->pluck('text')
            ->all());
    }

    #[Test]
    public function it_accepts_only_a_declared_type_in_a_writable_morph_type_column(): void
    {
        $shelf = $this->postJson('/api/labels/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['text' => 'Shelf', 'labelable_type' => 'shelf', 'labelable_id' => 101]]]]);
        $comment = $this->postJson('/api/labels/mutate', ['mutate' => [['operation' => 'create', 'attributes' => ['text' => 'Comment', 'labelable_type' => 'comment', 'labelable_id' => 101]]]]);

        $shelf->assertOk();
        $this->assertExactErrors($comment, ['mutate.0.attributes.labelable_type' => ['The selected mutate.0.attributes.labelable_type is invalid.']]);
        $this->assertSame(['Shelf'], Label::query()
            ->pluck('text')
            ->all());
    }
}
