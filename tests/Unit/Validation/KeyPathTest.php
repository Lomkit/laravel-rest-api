<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Lomkit\Rest\Validation\KeyPath;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class KeyPathTest extends TestCase
{
    #[Test]
    public function it_escapes_the_dots_of_a_key_segment(): void
    {
        $this->assertSame('a\.b', KeyPath::segment('a.b'));
        $this->assertSame('3', KeyPath::segment(3));
        $this->assertSame(['mutate', 'a.b', 'operation'], KeyPath::segments('mutate.a\.b.operation'));
    }

    #[Test]
    public function it_reads_a_key_segment_by_segment(): void
    {
        $data = ['mutate' => ['a.b' => ['operation' => 'create']], 'mutate.a' => ['b' => ['operation' => 'update']]];

        $this->assertTrue(KeyPath::has($data, 'mutate.a\.b.operation'));
        $this->assertSame('create', KeyPath::get($data, 'mutate.a\.b.operation'));
        $this->assertFalse(KeyPath::has($data, 'mutate.a.b.operation'));
        $this->assertNull(KeyPath::get($data, 'mutate.a.b.operation'));
    }

    #[Test]
    public function it_ignores_a_root_key_written_as_a_path(): void
    {
        $data = ['search' => ['limit' => 10], 'search.limit' => 'ten', 'search.page' => 2];

        $this->assertSame(10, KeyPath::get($data, 'search.limit'));
        $this->assertFalse(KeyPath::has($data, 'search.page'));
    }

    #[Test]
    public function it_writes_a_key_segment_by_segment(): void
    {
        $data = ['fields' => [['name' => 'status', 'value' => 'draft']]];

        KeyPath::set($data, 'fields.status', 'draft');
        KeyPath::set($data, 'meta.a\.b', 1);

        $this->assertSame(['fields' => [['name' => 'status', 'value' => 'draft'], 'status' => 'draft'], 'meta' => ['a.b' => 1]], $data);
    }

    #[Test]
    public function it_does_not_name_keys_that_a_rule_key_cannot_hold(): void
    {
        $this->assertTrue(KeyPath::canName('a.b'));
        $this->assertTrue(KeyPath::canName(0));
        $this->assertFalse(KeyPath::canName('*'));
        $this->assertFalse(KeyPath::canName('a*'));
        $this->assertFalse(KeyPath::canName('a\\'));
    }
}
