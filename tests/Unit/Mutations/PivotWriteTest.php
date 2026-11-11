<?php

namespace Lomkit\Rest\Tests\Unit\Mutations;

use Lomkit\Rest\Exceptions\PivotPersisterException;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Mutations\MutationNode;
use Lomkit\Rest\Mutations\PivotWrite;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PivotWriteTest extends PackageTestCase
{
    #[Test]
    public function it_refuses_a_write_that_names_no_row(): void
    {
        $this->expectExceptionObject(PivotPersisterException::noRelatedRows('tags'));

        new PivotWrite(new Ticket, 'tags', [], [], self::entry(MutationOperation::Attach));
    }

    #[Test]
    public function it_refuses_a_relation_that_is_not_many_to_many(): void
    {
        $write = new PivotWrite(new Ticket, 'team', [self::tag(7)], [], self::entry(MutationOperation::Attach));

        $this->expectExceptionObject(PivotPersisterException::notManyToMany(Ticket::class, 'team'));

        $write->pivots();
    }

    #[Test]
    #[DataProvider('keyPaths')]
    public function it_names_the_rows_and_the_key_path_of_its_entry(MutationOperation $operation, string $path): void
    {
        $write = new PivotWrite(new Ticket, 'tags', [self::tag(7), self::tag(8)], ['weight' => 2], self::entry($operation));

        $this->assertSame([7, 8], $write->keys());
        $this->assertSame($path, $write->keyPath());
    }

    /** @return array<string, array{MutationOperation, string}> */
    public static function keyPaths(): array
    {
        return [
            'create' => [MutationOperation::Create, 'mutate.0.relations.tags.1'],
            'attach' => [MutationOperation::Attach, 'mutate.0.relations.tags.1.key'],
            'sync' => [MutationOperation::Sync, 'mutate.0.relations.tags.1.key'],
        ];
    }

    private static function entry(MutationOperation $operation): MutationNode
    {
        return new MutationNode($operation, null, null, null, null, null, 1, path: 'mutate.0.relations.tags.1');
    }

    private static function tag(int $key): Tag
    {
        return (new Tag)->forceFill(['id' => $key]);
    }
}
