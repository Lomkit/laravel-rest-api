<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Restrictions;

use Illuminate\Auth\GenericUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ClausedExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstHalfExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\GuestlessExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictedAuthorController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictedAuthorResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondHalfExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WideningExtension;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorController;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\NotingExtension;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class RestrictionGroupsTest extends PackageTestCase
{
    use CapturesQueries;

    private const string ROOT = 'select authors.id from authors where %sauthors.deleted_at is null order by authors.id desc limit 50 offset 0';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RestrictionLog::reset();
        ExtensionLog::reset();
        ClausedExtension::$clause = null;
        Rest::resource('path-authors', PathAuthorController::class)->register();
        Rest::resource('restricted-authors', RestrictedAuthorController::class)->register();
        DB::table('authors')->insert([
            ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-1'],
            ['id' => 2, 'first_name' => 'Alan', 'last_name' => 'Hidden', 'api_token' => 'token-2'],
            ['id' => 3, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-3'],
            ['id' => 4, 'first_name' => 'Mary', 'last_name' => 'Shelley', 'api_token' => 'token-4'],
        ]);
        DB::table('posts')->insert([
            ['id' => 1, 'author_id' => 1, 'title' => 'Notes', 'status' => 'published', 'views' => 1, 'published_on' => '2026-01-01'],
            ['id' => 2, 'author_id' => 3, 'title' => 'Compilers', 'status' => 'published', 'views' => 2, 'published_on' => '2026-01-01'],
        ]);
        DB::table('books')->insert([['id' => 1, 'title' => 'Dune'], ['id' => 2, 'title' => 'Hidden'], ['id' => 3, 'title' => 'Emma']]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        RestrictionLog::reset();
        ExtensionLog::reset();
        ClausedExtension::$clause = null;

        parent::tearDown();
    }

    #[Test]
    public function it_keeps_an_or_where_of_a_restriction_inside_its_group(): void
    {
        config(['rest.extensions' => ['wide' => WideningExtension::class]]);
        $this->captureQueries();

        $response = $this->postJson('/path-authors/search', ['search' => ['selects' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [4, 3, 1]);
        $this->assertSame([[sprintf(self::ROOT, '((last_name != ?) and (authors.last_name != ?)) and '), ['Hidden', 'Concealed']]], $this->readsOf('authors'));
    }

    #[Test]
    public function it_passes_a_guest_as_null(): void
    {
        config(['rest.extensions' => ['guestless' => GuestlessExtension::class]]);

        $guest = $this->postJson('/restricted-authors/search', ['search' => ['selects' => [['field' => 'id']]]]);
        $user = $this->actingAs(new GenericUser(['id' => 1]))->postJson('/restricted-authors/search', ['search' => ['selects' => [['field' => 'id']]]]);

        $guest->assertOk()->assertJsonPath('data', [])->assertJsonPath('total', 0);
        $user->assertOk()->assertJsonPath('data.*.id', [4, 3, 2, 1]);
    }

    #[Test]
    public function it_passes_the_acting_user_as_is(): void
    {
        config(['rest.extensions' => ['hidden' => HiddenRowsExtension::class]]);
        $requester = new GenericUser(['id' => 5]);
        $searcher = new GenericUser(['id' => 6]);

        $this->actingAs($requester)->postJson('/restricted-authors/search', ['search' => []])->assertOk();
        $rows = Rest::search(RestrictedAuthorResource::class)->as($searcher)->payload([])->page();

        $this->assertSame([2, 3], [count(RestrictionLog::$users), $rows->total()]);
        $this->assertSame($requester, RestrictionLog::$users[0]);
        $this->assertSame($searcher, RestrictionLog::$users[1]);
    }

    #[Test]
    public function it_restricts_a_resource_marked_without_perimeter(): void
    {
        config(['rest.extensions' => ['hidden' => HiddenRowsExtension::class]]);

        $response = $this->postJson('/api/books/search', ['search' => []]);

        $response->assertOk()->assertJsonPath('data.*.id', [3, 1]);
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_gives_the_same_rows_and_sql_in_any_extension_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        $this->captureQueries();

        $response = $this->postJson('/restricted-authors/search', ['search' => ['selects' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [4, 2]);
        $this->assertSame([[sprintf(self::ROOT, '((authors.id != ?) and (authors.id != ?)) and '), [1, 3]]], $this->readsOf('authors'));
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [['half_a' => FirstHalfExtension::class, 'half_b' => SecondHalfExtension::class]],
            'in reverse order' => [['half_b' => SecondHalfExtension::class, 'half_a' => FirstHalfExtension::class]],
        ];
    }

    #[Test]
    public function it_adds_no_group_for_an_extension_of_another_kind(): void
    {
        config(['rest.extensions' => ['noting' => NotingExtension::class]]);
        $this->captureQueries();

        $this->postJson('/restricted-authors/search', ['search' => ['selects' => [['field' => 'id']]]])->assertOk();

        $this->assertSame([[sprintf(self::ROOT, ''), []]], $this->readsOf('authors'));
        $this->assertSame([NotingExtension::class], ExtensionLog::$built);
    }

    #[Test]
    public function it_accepts_a_restriction_through_another_table(): void
    {
        config(['rest.extensions' => ['claused' => ClausedExtension::class]]);
        ClausedExtension::$clause = static fn (Builder $query): Builder => $query->whereHas('posts', static fn (Builder $posts): Builder => $posts->where('title', 'Notes'));

        $response = $this->postJson('/restricted-authors/search', ['search' => ['selects' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1]);
    }
}
