<?php

namespace Lomkit\Rest\Tests\Unit\Validation\Search;

use Lomkit\Rest\Validation\Search\FilterTally;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FilterTallyTest extends TestCase
{
    #[Test]
    #[DataProvider('relationLists')]
    public function it_counts_the_relation_filters_of_a_list(mixed $filters, int $depth, int $count): void
    {
        $this->assertSame($count, (new FilterTally($depth))->of($filters)->relations);
    }

    #[Test]
    #[DataProvider('patternLists')]
    public function it_counts_the_pattern_filters_of_a_list(mixed $filters, int $depth, int $count): void
    {
        $this->assertSame($count, (new FilterTally($depth))->of($filters)->patterns);
    }

    #[Test]
    public function it_counts_both_totals_in_one_walk(): void
    {
        $counts = (new FilterTally(1))->of([
            ['field' => 'author.first_name', 'operator' => 'like', 'value' => 'A%'],
            ['relation' => 'tags', 'nested' => [['field' => 'name', 'operator' => 'ilike', 'value' => 'e%']]],
            ['field' => 'title', 'operator' => 'not like', 'value' => '%notes'],
        ]);

        $this->assertSame(['relations' => 2, 'patterns' => 3], get_object_vars($counts));
    }

    /** @return array<string, array{mixed, int, int}> */
    public static function relationLists(): array
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

    /** @return array<string, array{mixed, int, int}> */
    public static function patternLists(): array
    {
        $title = ['field' => 'title', 'value' => 'Engines'];
        $like = ['field' => 'title', 'operator' => 'like', 'value' => 'En%'];

        return [
            'filters without an operator' => [[$title, ['field' => 'views', 'value' => 1]], 1, 0],
            'filters with other operators' => [[
                ['field' => 'views', 'operator' => '>', 'value' => 1],
                ['field' => 'title', 'operator' => 'in', 'value' => ['Engines']],
                ['field' => 'title', 'operator' => 'is null'],
            ], 1, 0],
            'each pattern operator' => [[
                $like,
                ['field' => 'title', 'operator' => 'not like', 'value' => 'En%'],
                ['field' => 'title', 'operator' => 'ilike', 'value' => 'en%'],
                ['field' => 'title', 'operator' => 'not ilike', 'value' => 'en%'],
                $title,
            ], 1, 4],
            'a pattern on a relation path' => [[['field' => 'author.first_name', 'operator' => 'like', 'value' => 'A%']], 1, 1],
            'an operator in capitals' => [[['field' => 'title', 'operator' => 'LIKE', 'value' => 'En%']], 1, 0],
            'an operator that is not a string' => [[['field' => 'title', 'operator' => ['like'], 'value' => 'En%']], 1, 0],
            'a nested group' => [[['nested' => [$like, $title, $like]]], 1, 2],
            'the nested filters of a relation group' => [[['relation' => 'posts', 'nested' => [$like, $title]]], 1, 1],
            'an operator next to a relation' => [[['relation' => 'posts', 'operator' => 'like']], 1, 0],
            'the types of a relation group' => [[['relation' => 'commentable', 'types' => ['post' => [$like], 'draft' => [$like, $title]]]], 1, 2],
            'types next to a field' => [[['field' => 'title', 'types' => ['post' => [$like]]]], 1, 0],
            'groups below the depth' => [[['nested' => [$like, ['nested' => [$like]]]]], 1, 1],
            'groups at depth zero' => [[$like, ['nested' => [$like]], ['relation' => 'posts', 'nested' => [$like]]], 0, 1],
            'groups at depth two' => [[['nested' => [$like, ['nested' => [$like]]]]], 2, 2],
            'entries that are not objects' => [['like', 3, null], 1, 0],
            'a list given as an object' => [['a' => $like], 1, 0],
            'a nested group given as an object' => [[['nested' => ['a' => $like]]], 1, 0],
            'no list' => ['like', 1, 0],
        ];
    }
}
