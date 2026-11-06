<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\Changes;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ChangesTest extends TestCase
{
    #[Test]
    public function it_has_a_key_that_is_set_to_null(): void
    {
        $changes = new Changes(['book_id' => null]);

        $this->assertSame([true, null], [$changes->has('book_id'), $changes->get('book_id')]);
    }

    #[Test]
    public function it_reads_null_for_a_key_that_it_does_not_have(): void
    {
        $changes = new Changes(['title' => 'Dune II']);

        $this->assertSame([false, null, 'Dune II'], [$changes->has('book_id'), $changes->get('book_id'), $changes->get('title')]);
    }

    #[Test]
    public function it_lists_its_keys_as_strings(): void
    {
        $changes = new Changes(['title' => 'Dune II', 7 => 'Classics']);

        $this->assertSame(['title', '7'], $changes->keys());
    }
}
