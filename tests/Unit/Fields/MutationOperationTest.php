<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Fields\WriteOperation;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class MutationOperationTest extends TestCase
{
    #[Test]
    #[DataProvider('operations')]
    public function it_maps_each_operation_to_its_write_and_its_key(MutationOperation $operation, ?WriteOperation $write, bool $requiresKey): void
    {
        $this->assertSame($write, $operation->writeOperation());
        $this->assertSame($requiresKey, $operation->requiresKey());
    }

    /** @return array<string, array{MutationOperation, ?WriteOperation, bool}> */
    public static function operations(): array
    {
        return [
            'create' => [MutationOperation::Create, WriteOperation::Create, false],
            'update' => [MutationOperation::Update, WriteOperation::Update, true],
            'attach' => [MutationOperation::Attach, null, true],
            'detach' => [MutationOperation::Detach, null, true],
            'sync' => [MutationOperation::Sync, WriteOperation::Update, true],
            'toggle' => [MutationOperation::Toggle, WriteOperation::Update, true],
        ];
    }

    #[Test]
    #[DataProvider('entries')]
    public function it_reads_the_operation_of_a_payload_entry(mixed $entry, ?MutationOperation $expected): void
    {
        $this->assertSame($expected, MutationOperation::tryFromEntry($entry));
    }

    /** @return array<string, array{mixed, ?MutationOperation}> */
    public static function entries(): array
    {
        return [
            'a known operation' => [['operation' => 'sync', 'key' => 1], MutationOperation::Sync],
            'an unknown operation' => [['operation' => 'replace'], null],
            'an operation that is not a string' => [['operation' => ['create']], null],
            'an entry without an operation' => [['attributes' => []], null],
            'an entry that is not an array' => ['create', null],
            'a missing entry' => [null, null],
        ];
    }
}
