<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Authorization\PreparedRows;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PreparedRowsTest extends TestCase
{
    #[Test]
    public function it_keeps_a_row_whose_cast_attribute_is_read(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows);

        $meta = $checks->rows->firstOrFail()->getAttribute('meta');

        $rows->ensureUnchanged('acl', $checks);
        $this->assertSame(['a' => 1], $meta->getArrayCopy());
    }

    #[Test]
    public function it_refuses_a_row_whose_cast_attribute_changes(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows);

        $checks->rows->firstOrFail()->getAttribute('meta')['a'] = 2;

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', $checks);
    }

    #[Test]
    public function it_names_the_extension_that_changed_the_rows(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows);
        $checks->rows->firstOrFail()->setAttribute('id', 2);

        try {
            $rows->ensureUnchanged('acl', $checks);
            $this->fail('The change was not refused.');
        } catch (InvalidAuthorizationExtensionException $exception) {
            $this->assertTrue($exception->namesExtension('acl'));
        }
    }

    private function checks(): PendingChecks
    {
        $model = new class extends Model
        {
            protected $table = 'documents';

            /** @return array<string, string> */
            #[\Override]
            protected function casts(): array
            {
                return ['meta' => AsArrayObject::class];
            }
        };
        $row = $model->newFromBuilder(['id' => 1, 'meta' => '{"a": 1}']);

        return new PendingChecks(new TicketResource, 'view', new Collection([$row]), null);
    }
}
