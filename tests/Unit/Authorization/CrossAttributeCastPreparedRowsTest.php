<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as BaseCollection;
use Lomkit\Rest\Authorization\LoadedRelationNames;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Authorization\PreparedRows;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\LabelCast;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\LabelledDocument;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

class CrossAttributeCastPreparedRowsTest extends TestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        LabelCast::$writes = 0;
    }

    #[Test]
    public function it_keeps_a_row_read_through_a_cast_that_reads_another_attribute(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $label = $this->label($checks);

        $rows->ensureUnchanged('acl', $checks);
        $this->assertSame(['code' => 'A', 'owner' => 'Ada'], $label->all());
    }

    #[Test]
    public function it_refuses_a_change_of_the_value_that_such_a_cast_reads(): void
    {
        $checks = $this->checks();
        $label = $this->label($checks);
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $label->put('code', 'B');

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', TicketResource::class, 'view'));

        $rows->ensureUnchanged('acl', $checks);
    }

    private function checks(): PendingChecks
    {
        $row = (new LabelledDocument)->newFromBuilder(['id' => 1, 'owner' => 'Ada', 'label' => 'A|0']);

        return new PendingChecks(new TicketResource, 'view', new Collection([$row]), null);
    }

    /** @return BaseCollection<string, mixed> */
    private function label(PendingChecks $checks): BaseCollection
    {
        $label = $checks->rows->firstOrFail()->getAttribute('label');

        return $label instanceof BaseCollection ? $label : $this->fail('The label is not a collection.');
    }
}
