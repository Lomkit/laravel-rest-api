<?php

namespace Lomkit\Rest\Tests\Feature\Authorization;

use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Draft;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class UuidRowGatesTest extends PackageTestCase
{
    private const string FIRST = '0a000000-0000-4000-8000-000000000001';

    private const string SECOND = 'c0000000-0000-4000-8000-000000000002';

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    public function it_answers_the_gates_of_rows_with_uuid_keys(): void
    {
        Author::create(['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-ada', 'settings' => null]);
        Draft::create(['id' => self::SECOND, 'author_id' => 1, 'title' => 'Compilers draft']);
        Draft::create(['id' => self::FIRST, 'author_id' => 1, 'title' => 'Engines draft']);
        $allowed = ['allowed' => true, 'message' => null];

        $response = $this->postJson('/api/authors/search', ['search' => [
            'selects' => [['field' => 'id']],
            'includes' => [['relation' => 'drafts', 'selects' => [['field' => 'id']], 'sorts' => [['field' => 'id']], 'gates' => ['view', 'update']]],
        ]]);

        $response
            ->assertOk()
            ->assertJsonPath('data.0.drafts', [
                ['id' => self::FIRST, 'gates' => ['authorized_to_view' => $allowed, 'authorized_to_update' => $allowed]],
                ['id' => self::SECOND, 'gates' => ['authorized_to_view' => $allowed, 'authorized_to_update' => $allowed]],
            ]);
    }
}
