<?php

namespace Lomkit\Rest\Tests\Unit\Http;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\RowCopies;
use Lomkit\Rest\Tests\Unit\Http\Stubs\CopiedRow;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RowCopiesTest extends TestCase
{
    #[Test]
    public function it_copies_rows_and_their_loaded_relations(): void
    {
        $row = self::stored(1, 'Annual report');
        $row->setRelation('parent', self::stored(2, 'Archive'));
        $row->setRelation('children', new Collection([5 => self::stored(3, 'Budget'), 7 => self::stored(4, 'Charter')]));
        $row->setRelation('owner', null);

        [$copy] = RowCopies::of([$row]);

        $this->assertNotSame($row, $copy);
        $this->assertInstanceOf(CopiedRow::class, $copy);
        $this->assertTrue($copy->exists);
        $this->assertSame(['id' => 1, 'title' => 'Annual report'], $copy->getAttributes());
        $this->assertSame([], $copy->getDirty());
        $this->assertNotSame($row->getRelation('parent'), $copy->getRelation('parent'));
        $this->assertSame(['id' => 2, 'title' => 'Archive'], $copy->getRelation('parent')->getAttributes());
        $this->assertInstanceOf(Collection::class, $copy->getRelation('children'));
        $this->assertSame([5, 7], $copy->getRelation('children')->keys()->all());
        $this->assertSame([3, 4], $copy->getRelation('children')->map(fn (Model $child): mixed => $child->getKey())->values()->all());
        $this->assertNotSame($row->getRelation('children')->first(), $copy->getRelation('children')->first());
        $this->assertNull($copy->getRelation('owner'));

        $copy->setAttribute('title', 'Changed');
        $copy->getRelation('parent')->setAttribute('title', 'Changed');

        $this->assertSame('Annual report', $row->getAttribute('title'));
        $this->assertSame('Archive', $row->getRelation('parent')->getAttribute('title'));
    }

    #[Test]
    public function it_copies_a_cycle_of_relations_once(): void
    {
        $first = self::stored(1, 'Annual report');
        $second = self::stored(2, 'Budget');
        $first->setRelation('next', $second);
        $second->setRelation('previous', $first);

        [$copy, $other] = RowCopies::of([$first, $second]);

        $this->assertSame($copy, $copy->getRelation('next')->getRelation('previous'));
        $this->assertSame($other, $copy->getRelation('next'));
        $this->assertNotSame($first, $copy);
    }

    #[Test]
    public function it_keeps_the_original_when_a_copied_cast_object_changes(): void
    {
        $row = new CopiedRow(['id' => 1, 'tags' => ['draft']]);
        $row->syncOriginal();
        $this->assertSame(['draft'], $row->getAttribute('tags')->all());

        [$copy] = RowCopies::of([$row]);
        $copy->getAttribute('tags')->push('final');

        $this->assertSame(['draft'], $row->getAttribute('tags')->all());
        $this->assertSame(['draft', 'final'], $copy->getAttribute('tags')->all());
    }

    #[Test]
    public function it_copies_each_call_apart(): void
    {
        $row = self::stored(1, 'Annual report');

        [$first] = RowCopies::of([$row]);
        [$second] = RowCopies::of([$row]);

        $this->assertNotSame($first, $second);
    }

    private static function stored(int $id, string $title): CopiedRow
    {
        $row = new CopiedRow(['id' => $id, 'title' => $title]);
        $row->exists = true;
        $row->syncOriginal();

        return $row;
    }
}
