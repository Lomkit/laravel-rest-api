<?php

namespace Lomkit\Rest\Tests\Unit\Validation\Search;

use Lomkit\Rest\Validation\Search\RelationFilterCount;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RelationFilterCountTest extends TestCase
{
    #[Test]
    #[DataProvider('filterLists')]
    public function it_counts_the_relation_filters_of_a_list(mixed $filters, int $depth, int $count): void
    {
        $this->assertSame($count, (new RelationFilterCount($depth))->of($filters));
    }

    /** @return array<string, array{mixed, int, int}> */
    public static function filterLists(): array
    {
        $title = ['field' => 'title', 'value' => 'Engines'];
        $author = ['field' => 'author.first_name', 'value' => 'Ada'];

        return [
            'fields of the resource' => [[$title, ['field' => 'views', 'value' => 1]], 1, 0],
            'dotted field paths' => [[$author, ['field' => 'author.posts.title', 'value' => 'Notes'], $title], 1, 2],
            'a pivot field of the include' => [[['field' => 'pivot.role', 'value' => 'editor']], 1, 0],
            'a pivot field through a relation' => [[['field' => 'tags.pivot.role', 'value' => 'editor']], 1, 1],
            'a relation group' => [[['relation' => 'author']], 1, 1],
            'a relation group that excludes' => [[['relation' => 'author', 'exists' => false]], 1, 1],
            'a relation group with a null relation' => [[['relation' => null]], 1, 1],
            'a nested group' => [[['nested' => [$author, $title, ['relation' => 'tags']]]], 1, 2],
            'the nested filters of a relation group' => [[['relation' => 'posts', 'nested' => [['field' => 'author.last_name', 'value' => 'Lovelace']]]], 1, 2],
            'the types of a relation group' => [[['relation' => 'commentable', 'types' => ['post' => [$author], 'draft' => [$author, $title]]]], 1, 3],
            'types next to a field' => [[['field' => 'title', 'types' => ['post' => [$author]]]], 1, 0],
            'groups below the depth' => [[['nested' => [$author, ['nested' => [$author]]]]], 1, 1],
            'groups at depth zero' => [[$author, ['nested' => [$author]], ['relation' => 'posts', 'nested' => [$author]]], 0, 2],
            'groups at depth two' => [[['nested' => [$author, ['nested' => [$author]]]]], 2, 2],
            'a field that is not a string' => [[['field' => ['author.first_name']], ['field' => 1]], 1, 0],
            'entries that are not objects' => [['author.first_name', 3, null], 1, 0],
            'a list given as an object' => [['a' => $author], 1, 0],
            'a nested group given as an object' => [[['nested' => ['a' => $author]]], 1, 0],
            'no list' => ['author.first_name', 1, 0],
        ];
    }
}
