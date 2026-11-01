<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use LogicException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\TargetKeysReadingController;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\UntypedDraftController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DeclaredKeyTypeTest extends PackageTestCase
{
    use CapturesQueries;

    private const string NAMED = '019a35f0-7c2e-7a41-9b3d-5e8f1c2a4b60';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('untyped-drafts', UntypedDraftController::class)->register();
        $author = Author::factory()->create(['id' => 1]);

        foreach ([self::NAMED => 'Spring plan', '019b0000-0000-4000-8000-000000000001' => 'Summer plan', 'a19b0000-0000-4000-8000-000000000002' => 'Autumn plan'] as $id => $title) {
            Draft::factory()
                ->for($author)
                ->create(['id' => $id, 'title' => $title]);
        }
    }

    #[Test]
    public function it_destroys_only_the_named_key_when_the_model_keeps_the_default_key_type(): void
    {
        $this->captureQueries();

        $response = $this->deleteJson('/untyped-drafts', ['resources' => [self::NAMED]]);

        $response->assertOk()->assertExactJson(['data' => [['id' => self::NAMED, 'title' => 'Spring plan']], 'meta' => []]);
        $this->assertCount(1, $this->keyQueries('drafts'));
        $this->assertSame([[self::NAMED], [self::NAMED]], array_column($this->readsOf('drafts'), 1));
        $this->assertSame(
            ['019b0000-0000-4000-8000-000000000001', 'a19b0000-0000-4000-8000-000000000002'],
            Draft::query()
                ->orderBy('id')
                ->pluck('id')
                ->all(),
        );
    }

    #[Test]
    public function it_acts_only_on_the_named_key_when_the_model_keeps_the_default_key_type(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/untyped-drafts/actions/retitle-untyped-drafts', ['resources' => [self::NAMED]]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 1]]);
        $this->assertCount(1, $this->keyQueries('drafts'));
        $this->assertSame([[self::NAMED], [self::NAMED]], array_column($this->readsOf('drafts'), 1));
        $this->assertSame(
            ['Retitled', 'Summer plan', 'Autumn plan'],
            Draft::query()
                ->orderBy('id')
                ->pluck('title')
                ->all(),
        );
    }

    #[Test]
    public function it_has_no_target_keys_on_an_action_that_is_not_targeted(): void
    {
        Rest::resource('reading-drafts', TargetKeysReadingController::class)->register();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(new LogicException('The request has no resource keys.'));

        $this->postJson('/reading-drafts/actions/count-untyped-drafts');
    }
}
