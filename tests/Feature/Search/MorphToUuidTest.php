<?php

namespace Lomkit\Rest\Tests\Feature\Search;

use Lomkit\Rest\Tests\Feature\Search\Stubs\Pinboard\Pin;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Pinboard\Pinboard;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class MorphToUuidTest extends PackageTestCase
{
    private const string DRAFT = 'c0000000-0000-4000-8000-000000000001';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Pinboard];
    }

    #[Test]
    public function it_loads_a_target_with_a_uuid_key(): void
    {
        Author::create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada', 'settings' => null]);
        Draft::create(['id' => self::DRAFT, 'author_id' => 1, 'title' => 'Engines draft']);
        Pin::create(['id' => 1, 'note' => 'first', 'pinnable_type' => Draft::class, 'pinnable_id' => self::DRAFT]);
        Pin::create(['id' => 2, 'note' => 'gone', 'pinnable_type' => Draft::class, 'pinnable_id' => '0a000000-0000-4000-8000-000000000002']);

        $response = $this->postJson('/api/pins/search', ['search' => ['includes' => [['relation' => 'pinnable']], 'sorts' => [['field' => 'id']]]]);

        $response->assertOk()->assertJsonPath('data.*.pinnable', [['@type' => Draft::class, 'id' => self::DRAFT, 'title' => 'Engines draft'], null]);
    }
}
