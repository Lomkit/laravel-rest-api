<?php

namespace Lomkit\Rest\Tests\Feature\Confirmation;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Events\ResourceDestroyed;
use Lomkit\Rest\Events\ResourceForceDestroyed;
use Lomkit\Rest\Events\ResourceMutated;
use Lomkit\Rest\Events\ResourceRestored;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ArchiveUser;
use Lomkit\Rest\Tests\Feature\Confirmation\Stubs\ConfirmedWriteController;
use Lomkit\Rest\Tests\Feature\Confirmation\Stubs\ConfirmedWriteResource;
use Lomkit\Rest\Tests\Feature\Confirmation\Stubs\StaffWritePolicy;
use Lomkit\Rest\Tests\Fixtures\Archive\Archive;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\PackageTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\Attributes\WithMigration;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithMigration]
#[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
#[WithConfig('auth.providers.users.model', ArchiveUser::class)]
class WriteConfirmationTest extends PackageTestCase
{
    private const array BEFORE = ['1 Budget', '2 Contract', '3 Invoice trashed', '4 Payroll trashed'];

    private const array EVENTS = [ResourceMutated::class, ResourceDestroyed::class, ResourceRestored::class, ResourceForceDestroyed::class];

    private const array WRITES = [
        'mutate' => [
            'method' => 'POST',
            'uri' => '/confirmed-writes/mutate',
            'body' => ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Budget 2027']], ['operation' => 'update', 'key' => 2, 'attributes' => ['title' => 'Contract 2027']]]],
            'other' => ['mutate' => [['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Budget 2028']], ['operation' => 'update', 'key' => 2, 'attributes' => ['title' => 'Contract 2027']]]],
            'titles' => ['Budget', 'Contract'],
            'written' => ['1 Budget 2027', '2 Contract 2027', '3 Invoice trashed', '4 Payroll trashed'],
            'hook' => 'mutatingAll',
            'event' => ResourceMutated::class,
        ],
        'destroy' => [
            'method' => 'DELETE',
            'uri' => '/confirmed-writes',
            'body' => ['resources' => [1, 2]],
            'other' => ['resources' => [2, 1]],
            'titles' => ['Budget', 'Contract'],
            'written' => ['1 Budget trashed', '2 Contract trashed', '3 Invoice trashed', '4 Payroll trashed'],
            'hook' => 'destroyingAll',
            'event' => ResourceDestroyed::class,
        ],
        'restore' => [
            'method' => 'POST',
            'uri' => '/confirmed-writes/restore',
            'body' => ['resources' => [3, 4]],
            'other' => ['resources' => [4, 3]],
            'titles' => ['Invoice', 'Payroll'],
            'written' => ['1 Budget', '2 Contract', '3 Invoice', '4 Payroll'],
            'hook' => 'restoringAll',
            'event' => ResourceRestored::class,
        ],
        'forceDestroy' => [
            'method' => 'DELETE',
            'uri' => '/confirmed-writes/force',
            'body' => ['resources' => [3, 4]],
            'other' => ['resources' => [4, 3]],
            'titles' => ['Invoice', 'Payroll'],
            'written' => ['1 Budget', '2 Contract'],
            'hook' => 'forceDestroyingAll',
            'event' => ResourceForceDestroyed::class,
        ],
    ];

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
        ConfirmedWriteResource::reset();
        Rest::resource('confirmed-writes', ConfirmedWriteController::class)
            ->withSoftDeletes()
            ->register();
        Gate::policy(Document::class, StaffWritePolicy::class);
        ArchiveUser::create(['id' => 1, 'name' => 'Ada', 'email' => 'ada@example.com', 'password' => 'secret']);
        $grace = ArchiveUser::create(['id' => 2, 'name' => 'Grace', 'email' => 'grace@staff.example', 'password' => 'secret']);
        ArchiveUser::create(['id' => 3, 'name' => 'Margaret', 'email' => 'margaret@staff.example', 'password' => 'secret']);

        foreach ([1 => 'Budget', 2 => 'Contract', 3 => 'Invoice', 4 => 'Payroll'] as $id => $title) {
            Document::create(['id' => $id, 'title' => $title, 'deleted_at' => $id > 2 ? '2026-11-01 09:00:00' : null]);
        }

        $this->actingAs($grace);
    }

    /** @return array<string, array{string}> */
    public static function writes(): array
    {
        return ['mutate' => ['mutate'], 'destroy' => ['destroy'], 'restore' => ['restore'], 'force destroy' => ['forceDestroy']];
    }

    #[Test]
    #[DataProvider('writes')]
    public function it_answers_428_with_a_token_and_writes_nothing(string $write): void
    {
        Event::fake(self::EVENTS);

        $response = $this->write($write);

        $response->assertStatus(428)->assertExactJson([
            'message' => "{$write}: 2 documents?",
            'confirmation' => ['token' => $this->tokenOf($response, $write), 'expires_at' => '2026-11-08T10:05:00Z', 'data' => ['titles' => self::WRITES[$write]['titles']]],
        ]);
        $this->assertSame([self::BEFORE, [$write], []], [self::state(), ConfirmedWriteResource::$asked, ConfirmedWriteResource::$hooks]);
        Event::assertNothingDispatched();
    }

    #[Test]
    #[DataProvider('writes')]
    public function it_writes_when_the_same_body_comes_back_with_the_token(string $write): void
    {
        Event::fake(self::EVENTS);
        $token = $this->tokenOf($this->write($write), $write);

        $response = $this->write($write, ['confirmation' => $token]);

        $response->assertOk();
        $this->assertSame([self::WRITES[$write]['written'], [$write, $write], [self::WRITES[$write]['hook']]], [self::state(), ConfirmedWriteResource::$asked, ConfirmedWriteResource::$hooks]);
        Event::assertDispatchedTimes(self::WRITES[$write]['event'], 1);
    }

    #[Test]
    #[DataProvider('writes')]
    public function it_writes_at_once_when_the_hook_gives_no_confirmation(string $write): void
    {
        ConfirmedWriteResource::$confirms = [];

        $response = $this->write($write);

        $response->assertOk();
        $this->assertSame([self::WRITES[$write]['written'], [$write]], [self::state(), ConfirmedWriteResource::$asked]);
    }

    #[Test]
    #[DataProvider('writes')]
    public function it_writes_as_before_on_a_resource_without_the_hook(string $write): void
    {
        $response = $this->write($write, ['confirmation' => 'unused'], '/api/documents');

        $response->assertOk();
        $this->assertSame([self::WRITES[$write]['written'], []], [self::state(), ConfirmedWriteResource::$asked]);
    }

    #[Test]
    public function it_reads_the_body_without_its_key_order_and_its_ignored_first_level_keys(): void
    {
        $token = $this->tokenOf($this->write('mutate'), 'mutate');
        $body = ['origin' => 'dialog', 'confirmation' => $token, 'mutate' => [
            ['attributes' => ['title' => 'Budget 2027'], 'key' => 1, 'operation' => 'update'],
            ['key' => 2, 'operation' => 'update', 'attributes' => ['title' => 'Contract 2027']],
        ]];

        $response = $this->postJson('/confirmed-writes/mutate', $body);

        $response->assertOk();
        $this->assertSame(self::WRITES['mutate']['written'], self::state());
    }

    #[Test]
    #[DataProvider('mismatches')]
    public function it_answers_a_new_428_when_the_token_does_not_match_the_request(string $write, string $mismatch): void
    {
        $token = $this->tokenOf($this->write($write), $write);
        $body = self::WRITES[$write]['body'];

        match ($mismatch) {
            'another body' => $body = self::WRITES[$write]['other'],
            'another user' => $this->actingAs(ArchiveUser::query()->findOrFail(3)),
            'an expired token' => $this->travel(300)->seconds(),
            'a changed signature' => $token = substr($token, 0, -1).(str_ends_with($token, '0') ? '1' : '0'),
            default => $token = '1794132300.forged',
        };

        $response = $this->send($write, [...$body, 'confirmation' => $token]);

        $response->assertStatus(428);
        $this->assertSame(self::BEFORE, self::state());
        $this->send($write, [...$body, 'confirmation' => $this->tokenOf($response, $write)])->assertOk();
    }

    /** @return array<string, array{string, string}> */
    public static function mismatches(): array
    {
        $cases = [];

        foreach (['mutate', 'destroy', 'restore', 'forceDestroy'] as $write) {
            foreach (['another body', 'another user', 'an expired token', 'a changed signature', 'a forged token'] as $mismatch) {
                $cases["{$write}, {$mismatch}"] = [$write, $mismatch];
            }
        }

        return $cases;
    }

    #[Test]
    public function it_answers_a_new_428_when_the_token_comes_from_another_write(): void
    {
        $token = $this->tokenOf($this->deleteJson('/confirmed-writes/force', ['resources' => [1, 2]]), 'forceDestroy');

        $response = $this->deleteJson('/confirmed-writes', ['resources' => [1, 2], 'confirmation' => $token]);

        $response->assertStatus(428)->assertJsonPath('message', 'destroy: 2 documents?');
        $this->assertSame(self::BEFORE, self::state());
    }

    #[Test]
    #[DataProvider('writes')]
    public function it_validates_the_body_before_it_asks_for_a_confirmation(string $write): void
    {
        $response = $this->write($write, ['confirmation' => 1794132300]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['confirmation' => ['The confirmation field must be a string.']]);
        $this->assertSame([], ConfirmedWriteResource::$asked);
    }

    #[Test]
    public function it_reads_the_confirmation_as_a_string_on_a_resource_without_the_hook(): void
    {
        $response = $this->deleteJson('/api/documents', ['resources' => [1], 'confirmation' => null]);

        $response->assertUnprocessable()->assertJsonPath('errors', ['confirmation' => ['The confirmation field must be a string.']]);
    }

    #[Test]
    #[DataProvider('writes')]
    public function it_refuses_the_rows_before_it_asks_for_a_confirmation(string $write): void
    {
        $this->actingAs(ArchiveUser::query()->findOrFail(1));

        $response = $this->write($write, ['confirmation' => 'confirmed']);

        $response->assertForbidden();
        $this->assertSame([self::BEFORE, []], [self::state(), ConfirmedWriteResource::$asked]);
    }

    #[Test]
    public function it_answers_409_before_it_asks_for_a_confirmation(): void
    {
        $entry = ['operation' => 'update', 'key' => 1, 'version' => '2026-11-01T09:00:00.000000Z', 'attributes' => ['title' => 'Budget 2027']];

        $stale = $this->postJson('/confirmed-writes/mutate', ['mutate' => [$entry]]);
        $current = $this->postJson('/confirmed-writes/mutate', ['mutate' => [[...$entry, 'version' => '2026-11-08T10:00:00.000000Z']]]);

        $stale->assertConflict();
        $current->assertStatus(428)->assertJsonPath('confirmation.data', ['titles' => ['Budget']]);
        $this->assertSame([self::BEFORE, ['mutate']], [self::state(), ConfirmedWriteResource::$asked]);
    }

    #[Test]
    #[DataProvider('writes')]
    #[WithConfig('rest.precognition.enabled', true)]
    public function it_stops_a_precognitive_request_before_the_confirmation(string $write): void
    {
        $response = $this->send($write, self::WRITES[$write]['body'], headers: ['Precognition' => 'true']);

        $response->assertNoContent();
        $this->assertSame([self::BEFORE, []], [self::state(), ConfirmedWriteResource::$asked]);
    }

    #[Test]
    public function it_says_which_writes_ask_for_a_confirmation_in_the_details(): void
    {
        $confirmed = $this->getJson('/confirmed-writes?with=confirmations');
        $plain = $this->getJson('/api/documents?with=confirmations');
        $base = $this->getJson('/confirmed-writes');

        $confirmed->assertOk()->assertJsonPath('data.confirmations', ['mutate' => true, 'destroy' => true, 'restore' => true, 'forceDestroy' => true]);
        $plain->assertOk()->assertJsonPath('data.confirmations', ['mutate' => false, 'destroy' => false, 'restore' => false, 'forceDestroy' => false]);
        $this->assertSame(['fields', 'limits', 'meta'], array_keys($base->json('data')));
    }

    /** @param array<string, mixed> $extra */
    private function write(string $write, array $extra = [], ?string $uri = null): TestResponse
    {
        return $this->send($write, [...self::WRITES[$write]['body'], ...$extra], $uri);
    }

    /**
     * @param  array<string, mixed>  $body
     * @param  array<string, string>  $headers
     */
    private function send(string $write, array $body, ?string $uri = null, array $headers = []): TestResponse
    {
        $uri ??= self::WRITES[$write]['uri'];

        if ($uri === '/api/documents') {
            $uri .= substr(self::WRITES[$write]['uri'], strlen('/confirmed-writes'));
        }

        return $this->json(self::WRITES[$write]['method'], $uri, $body, $headers);
    }

    private function tokenOf(TestResponse $response, string $write): string
    {
        $response->assertStatus(428)->assertJsonPath('message', "{$write}: 2 documents?");
        $token = $response->json('confirmation.token');
        $this->assertIsString($token);
        $this->assertMatchesRegularExpression('/^\d{10}\.[0-9a-f]{64}$/', $token);

        return $token;
    }

    /** @return list<string> */
    private static function state(): array
    {
        return Document::withTrashed()
            ->orderBy('id')
            ->get()
            ->map(fn (Document $document): string => trim("{$document->id} {$document->title} ".($document->trashed() ? 'trashed' : '')))
            ->all();
    }
}
