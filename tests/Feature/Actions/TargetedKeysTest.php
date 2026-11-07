<?php

namespace Lomkit\Rest\Tests\Feature\Actions;

use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Fixtures\Workspace\Note;
use Lomkit\Rest\Tests\Fixtures\Workspace\Workspace;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class TargetedKeysTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Workspace];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Note::factory()->create(['id' => 1, 'team' => 'red', 'title' => 'Standup']);
        Note::factory()->create(['id' => 2, 'team' => 'red', 'title' => 'Retro']);
        Note::factory()->create(['id' => 3, 'team' => 'blue', 'title' => 'Roadmap']);
    }

    #[Test]
    public function it_acts_on_the_named_keys(): void
    {
        $response = $this->pin([1, 2]);

        $response->assertOk()->assertExactJson(['data' => ['impacted' => 2, 'result' => null]]);

        $this->assertSame([1, 2], $this->pinned());
    }

    #[Test]
    public function it_refuses_a_key_outside_the_perimeter(): void
    {
        $response = $this->pin([1, 3]);

        $this->assertExactErrors($response, ['resources.1' => ['The selected resources.1 is invalid.']]);
        $this->assertSame([], $this->pinned());
    }

    #[Test]
    public function it_refuses_a_key_of_another_type(): void
    {
        $response = $this->pin(['one']);

        $this->assertExactErrors($response, ['resources.0' => ['The resources.0 field must be an integer.']]);
    }

    #[Test]
    public function it_refuses_repeated_keys(): void
    {
        $response = $this->pin([1, '1']);

        $this->assertExactErrors($response, [
            'resources.0' => ['The resources.0 field has a duplicate value.'],
            'resources.1' => ['The resources.1 field has a duplicate value.'],
        ]);
        $this->assertSame([], $this->pinned());
    }

    #[Test]
    public function it_refuses_an_object_of_keys(): void
    {
        $response = $this->pin(['first' => 1]);

        $this->assertExactErrors($response, ['resources' => ['The resources field must be a list.']]);
    }

    #[Test]
    public function it_refuses_more_keys_than_the_action_allows(): void
    {
        $this->captureQueries();

        $response = $this->pin([1, 2, 3, 4]);

        $this->assertExactErrors($response, ['resources' => ['The resources field must not have more than 3 items.']]);
        $this->assertSame([], $this->readsOf('notes'));
    }

    #[Test]
    public function it_checks_the_keys_with_one_query(): void
    {
        $this->captureQueries();

        $this->pin([1, 2])->assertOk();

        $this->assertCount(1, $this->keyQueries('notes'));
        $this->assertSame(['red'], $this->keyQueries('notes')[0][1]);
    }

    /** @param array<array-key, mixed> $resources */
    private function pin(array $resources): TestResponse
    {
        return $this->postJson('/api/team-notes/actions/pin-named-notes', ['resources' => $resources]);
    }

    /** @return list<int> */
    private function pinned(): array
    {
        return Note::query()
            ->where('pinned', true)
            ->orderBy('id')
            ->pluck('id')
            ->all();
    }
}
