<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Actions\ActionConfirmation;
use Lomkit\Rest\Actions\ActionTarget;
use Lomkit\Rest\Actions\QueuedAction;
use Lomkit\Rest\Events\ActionOperated;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ConfirmedDocumentController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ConfirmedDocumentResource;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ShredDocuments;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\StaffArchivePolicy;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithMigration('queue')]
#[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
#[WithConfig('auth.providers.users.model', ArchiveUser::class)]
class ActionConfirmationTest extends PackageTestCase
{
    private const string SHRED = '/confirmed-documents/actions/shred-documents';

    private const string TOKEN = '1794132300.1fd50fa4edba5784fb3ac2b71662fcbc9be3b716fba0383ddd6a4b6dd652bfb1';

    private ArchiveUser $grace;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Archive];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo('2026-11-08 10:00:00');
        ConfirmedDocumentResource::reset();
        Rest::resource('confirmed-documents', ConfirmedDocumentController::class)->register();
        Gate::policy(Document::class, StaffArchivePolicy::class);
        ArchiveUser::create(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $this->grace = ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@staff.example', 'password' => 'secret']);
        ArchiveUser::create(['id' => 3, 'name' => 'Margaret', 'email' => 'margaret@staff.example', 'password' => 'secret']);

        foreach ([1 => 'Budget', 2 => 'Contract', 3 => 'Invoice', 4 => 'Payroll'] as $id => $title) {
            Document::create(['id' => $id, 'title' => $title]);
        }

        $this->actingAs($this->grace);
    }

    #[Test]
    public function it_answers_428_with_a_token_and_runs_nothing(): void
    {
        Event::fake([ActionOperated::class]);

        $response = $this->postJson(self::SHRED);

        $response->assertStatus(428)->assertExactJson([
            'message' => 'Shred 4 documents?',
            'confirmation' => ['token' => self::TOKEN, 'expires_at' => '2026-11-08T10:05:00Z', 'data' => ['rows' => 4]],
        ]);
        $this->assertSame([[4], [], []], [ShredDocuments::$asked, ShredDocuments::$runs, ConfirmedDocumentResource::$hooks]);
        Event::assertNotDispatched(ActionOperated::class);
    }

    #[Test]
    public function it_runs_the_action_when_the_same_body_comes_back_with_the_token(): void
    {
        Event::fake([ActionOperated::class]);
        $body = ['search' => ['filters' => [['field' => 'title', 'operator' => '!=', 'value' => 'Budget']]], 'fields' => [['name' => 'note', 'value' => 'Year end']]];
        $token = $this->tokenOf($this->postJson(self::SHRED, $body), 3);

        $response = $this->postJson(self::SHRED, [...$body, 'confirmation' => $token]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
        $this->assertSame([[3, 3], [3]], [ShredDocuments::$asked, array_map(count(...), ShredDocuments::$runs)]);
        $this->assertSame(['operating'], ConfirmedDocumentResource::$hooks);
        Event::assertDispatchedTimes(ActionOperated::class, 1);
    }

    #[Test]
    public function it_reads_the_body_without_its_key_order_and_its_unknown_first_level_keys(): void
    {
        $token = $this->tokenOf($this->postJson(self::SHRED, ['search' => ['filters' => [['field' => 'title', 'operator' => '!=', 'value' => 'Budget']], 'limit' => 10]]), 3);

        $response = $this->postJson(self::SHRED, ['confirmation' => $token, 'origin' => 'dialog', 'search' => ['limit' => 10, 'filters' => [['value' => 'Budget', 'operator' => '!=', 'field' => 'title']]]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 3, 'result' => null]]);
    }

    #[Test]
    #[DataProvider('mismatches')]
    public function it_answers_a_new_428_when_the_token_does_not_match_the_request(string $mismatch): void
    {
        Queue::fake();
        $body = ['fields' => [['name' => 'note', 'value' => 'Year end']]];
        $token = $this->tokenOf($this->postJson(self::SHRED, $body));
        $path = self::SHRED;

        match ($mismatch) {
            'another body' => $body = ['fields' => [['name' => 'note', 'value' => 'Spring']]],
            'another user' => $this->actingAs(ArchiveUser::query()->findOrFail(3)),
            'another action' => $path = '/confirmed-documents/actions/queued-shred-documents',
            'an expired token' => $this->travel(300)->seconds(),
            'a changed signature' => $token = substr($token, 0, -1).(str_ends_with($token, '0') ? '1' : '0'),
            'a changed expiry' => $token = '1794132360'.substr($token, 10),
            'a token of another form' => $token = 'confirmed',
            default => null,
        };

        $response = $this->postJson($path, [...$body, 'confirmation' => $token]);

        $response->assertStatus(428)->assertJsonPath('message', 'Shred 4 documents?');
        $this->assertSame([], ShredDocuments::$runs);
        Queue::assertNothingPushed();
        $this->postJson($path, [...$body, 'confirmation' => $this->tokenOf($response)])->assertOk();
    }

    /** @return array<string, array{string}> */
    public static function mismatches(): array
    {
        return [
            'another body' => ['another body'],
            'another user' => ['another user'],
            'another action' => ['another action'],
            'an expired token' => ['an expired token'],
            'a changed signature' => ['a changed signature'],
            'a changed expiry' => ['a changed expiry'],
            'a token of another form' => ['a token of another form'],
        ];
    }

    #[Test]
    public function it_accepts_the_token_until_it_expires(): void
    {
        ShredDocuments::$seconds = 60;
        $first = $this->postJson(self::SHRED);
        $first->assertStatus(428)->assertJsonPath('confirmation.expires_at', '2026-11-08T10:01:00Z');
        $this->travel(59)->seconds();

        $response = $this->postJson(self::SHRED, ['confirmation' => $this->tokenOf($first)]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4, 'result' => null]]);
    }

    #[Test]
    public function it_answers_a_new_428_when_the_number_of_rows_changed(): void
    {
        $token = $this->tokenOf($this->postJson(self::SHRED));
        Document::create(['id' => 5, 'title' => 'Receipt']);

        $response = $this->postJson(self::SHRED, ['confirmation' => $token]);

        $response->assertStatus(428)->assertJsonPath('message', 'Shred 5 documents?')->assertJsonPath('confirmation.data', ['rows' => 5]);
        $this->assertSame([[4, 5], []], [ShredDocuments::$asked, ShredDocuments::$runs]);
    }

    #[Test]
    public function it_runs_at_once_when_the_action_gives_no_confirmation(): void
    {
        ShredDocuments::$from = 5;

        $response = $this->postJson(self::SHRED);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4, 'result' => null]]);
        $this->assertSame([[4], [[1, 2, 3, 4]]], [ShredDocuments::$asked, ShredDocuments::$runs]);
    }

    /** @param array<string, mixed> $body */
    #[Test]
    #[DataProvider('searches')]
    public function it_counts_the_rows_that_the_search_matches_within_the_perimeter(?int $max, array $body, int $rows): void
    {
        ConfirmedDocumentResource::$max = $max;
        Document::query()
            ->whereKey(4)
            ->update(['archived' => true]);

        foreach (range(5, 14) as $id) {
            Document::create(['id' => $id, 'title' => "Receipt {$id}"]);
        }

        $response = $this->postJson(self::SHRED, $body);

        $response->assertStatus(428)->assertJsonPath('confirmation.data', ['rows' => $rows]);
    }

    /** @return array<string, array{?int, array<string, mixed>, int}> */
    public static function searches(): array
    {
        return [
            'every row under a cap' => [1000, [], 13],
            'every row without a cap' => [null, [], 13],
            'a filter' => [1000, ['search' => ['filters' => [['field' => 'title', 'value' => 'Invoice']]]], 1],
            'a limit' => [1000, ['search' => ['limit' => 10]], 10],
            'a sort without a cap' => [null, ['search' => ['sorts' => [['field' => 'title']]]], 13],
        ];
    }

    #[Test]
    public function it_counts_the_keys_of_a_resources_target(): void
    {
        ConfirmedDocumentResource::$target = ActionTarget::Resources;
        $token = $this->tokenOf($this->postJson(self::SHRED, ['resources' => [3, 1]]), 2);

        $response = $this->postJson(self::SHRED, ['resources' => [3, 1], 'confirmation' => $token]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);
        $this->assertSame([[2, 2], [[3, 1]]], [ShredDocuments::$asked, ShredDocuments::$runs]);
    }

    #[Test]
    public function it_counts_no_row_for_an_action_without_rows(): void
    {
        $first = $this->postJson('/confirmed-documents/actions/import-documents');
        $token = $this->tokenOf($first, 0);
        $this->assertSame('{"message":"Import the documents?","confirmation":{"token":"'.$token.'","expires_at":"2026-11-08T10:05:00Z","data":{}}}', $first->getContent());

        $response = $this->postJson('/confirmed-documents/actions/import-documents', ['confirmation' => $token]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 0, 'result' => null]]);
        $this->assertSame([[0, 0], [[]]], [ShredDocuments::$asked, ShredDocuments::$runs]);
    }

    #[Test]
    public function it_refuses_the_rows_before_it_asks_for_a_confirmation(): void
    {
        $this->actingAs(ArchiveUser::query()->findOrFail(1));

        $response = $this->postJson(self::SHRED);

        $response->assertForbidden()->assertJsonPath('message', 'Only the staff shreds documents.');
        $this->assertSame([], ShredDocuments::$asked);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, list<string>>  $errors
     */
    #[Test]
    #[DataProvider('invalidBodies')]
    public function it_validates_the_body_before_it_asks_for_a_confirmation(array $body, array $errors): void
    {
        $response = $this->postJson(self::SHRED, $body);

        $response->assertUnprocessable()->assertJsonPath('errors', $errors);
        $this->assertSame([], ShredDocuments::$asked);
    }

    /** @return array<string, array{array<string, mixed>, array<string, list<string>>}> */
    public static function invalidBodies(): array
    {
        return [
            'a confirmation that is not a string' => [['confirmation' => 1794132300], ['confirmation' => ['The confirmation field must be a string.']]],
            'a confirmation set to null' => [['confirmation' => null], ['confirmation' => ['The confirmation field must be a string.']]],
            'an unknown filter field' => [['search' => ['filters' => [['field' => 'shelf', 'value' => 'A']]], 'confirmation' => 'confirmed'], ['search.filters.0.field' => ['The selected search.filters.0.field is invalid.']]],
        ];
    }

    #[Test]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_stops_a_precognitive_request_before_the_confirmation(): void
    {
        $response = $this->postJson(self::SHRED, [], ['Precognition' => 'true']);

        $response->assertNoContent();
        $this->assertSame([], ShredDocuments::$asked);
    }

    #[Test]
    public function it_queues_nothing_before_the_confirmation(): void
    {
        Queue::fake();
        $token = $this->tokenOf($this->postJson('/confirmed-documents/actions/queued-shred-documents'));
        Queue::assertNothingPushed();

        $response = $this->postJson('/confirmed-documents/actions/queued-shred-documents', ['confirmation' => $token]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 4, 'result' => null]]);
        Queue::assertPushed(QueuedAction::class, 1);
    }

    #[Test]
    public function it_starts_no_batch_before_the_confirmation(): void
    {
        Bus::fake();
        $token = $this->tokenOf($this->postJson('/confirmed-documents/actions/batched-shred-documents'));
        Bus::assertNothingBatched();
        $this->assertSame([], ShredDocuments::$batches);

        $response = $this->postJson('/confirmed-documents/actions/batched-shred-documents', ['confirmation' => $token]);

        $response->assertOk()->assertJsonPath('data.impacted', 4);
        Bus::assertBatchCount(1);
        $this->assertSame(['batched-shred-documents'], ShredDocuments::$batches);
    }

    #[Test]
    #[WithConfig('app.key', null)]
    public function it_asks_for_an_application_key_to_sign_the_token(): void
    {
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidConfigException::missingAppKey());

        $this->postJson(self::SHRED);
    }

    #[Test]
    #[WithConfig('app.key', null)]
    public function it_needs_no_application_key_when_the_action_runs_at_once(): void
    {
        ShredDocuments::$from = 5;

        $this->postJson(self::SHRED)->assertOk();
    }

    #[Test]
    public function it_says_which_actions_ask_for_a_confirmation_in_the_details(): void
    {
        $response = $this->getJson('/confirmed-documents');

        $response->assertOk();
        $this->assertSame(
            ['shred-documents' => true, 'queued-shred-documents' => true, 'batched-shred-documents' => true, 'import-documents' => true, 'touch-documents' => false],
            array_column($response->json('data.actions'), 'confirmation', 'uriKey'),
        );
    }

    #[Test]
    public function it_refuses_a_confirmation_with_a_list_of_data_or_no_lifetime(): void
    {
        $this->assertEquals(InvalidActionException::confirmationDataList(), $this->thrown(fn () => ActionConfirmation::make('Shred?')->data([4, 9])));
        $this->assertEquals(InvalidActionException::confirmationExpiry(0), $this->thrown(fn () => ActionConfirmation::make('Shred?')->expiresIn(0)));
    }

    private function tokenOf(TestResponse $response, int $rows = 4): string
    {
        $response->assertStatus(428)->assertJsonPath('confirmation.data', $rows === 0 ? [] : ['rows' => $rows]);
        $token = $response->json('confirmation.token');
        $this->assertIsString($token);

        return $token;
    }

    private function thrown(callable $build): ?\Throwable
    {
        try {
            $build();
        } catch (\Throwable $thrown) {
            return $thrown;
        }

        return null;
    }
}
