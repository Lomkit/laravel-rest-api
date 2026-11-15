<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Crypt;
use Lomkit\Rest\Authorization\LoadedRelationNames;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Authorization\PreparedRows;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\PreparedDocument;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
class EncryptedPreparedRowsTest extends TestCase
{
    #[Test]
    public function it_keeps_a_row_whose_encrypted_cast_attribute_is_read(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $secret = $checks->rows->firstOrFail()->getAttribute('secret');

        $rows->ensureUnchanged('acl', $checks);
        $this->assertSame(['a' => 1], $secret->getArrayCopy());
    }

    #[Test]
    public function it_keeps_a_row_whose_encrypted_cast_attribute_was_read_before_the_preparation(): void
    {
        $checks = $this->checks();
        $checks->rows->firstOrFail()->getAttribute('secret');
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $checks->rows->firstOrFail()->getAttribute('secret');

        $rows->ensureUnchanged('acl', $checks);
        $this->assertSame(['a' => 1], $checks->rows->firstOrFail()->getAttribute('secret')->getArrayCopy());
    }

    #[Test]
    public function it_keeps_a_row_that_the_hook_read_for_an_extension_that_does_nothing(): void
    {
        $checks = $this->checks();
        $start = LoadedRelationNames::of($checks->rows);
        $checks->rows->firstOrFail()->getAttribute('secret');

        $rows = PreparedRows::of($checks->rows, $start);

        $rows->ensureUnchanged('acl', $checks);
        $this->assertSame(['a' => 1], $checks->rows->firstOrFail()->getAttribute('secret')->getArrayCopy());
    }

    #[Test]
    public function it_refuses_a_row_whose_encrypted_cast_attribute_changes_in_place(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $checks->rows->firstOrFail()->getAttribute('secret')['a'] = 2;

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', $checks);
    }

    #[Test]
    public function it_refuses_a_row_whose_encrypted_cast_attribute_changes_after_the_hook_read_it(): void
    {
        $checks = $this->checks();
        $start = LoadedRelationNames::of($checks->rows);
        $checks->rows->firstOrFail()->getAttribute('secret');
        $rows = PreparedRows::of($checks->rows, $start);

        $checks->rows->firstOrFail()->getAttribute('secret')['b'] = 2;

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', $checks);
    }

    private function checks(): PendingChecks
    {
        $row = (new PreparedDocument)->newFromBuilder(['id' => 1, 'secret' => Crypt::encryptString('{"a": 1}')]);

        return new PendingChecks(new TicketResource, 'view', new Collection([$row]), null);
    }
}
