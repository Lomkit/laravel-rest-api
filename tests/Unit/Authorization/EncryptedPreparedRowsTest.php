<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use ArrayAccess;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Crypt;
use Lomkit\Rest\Authorization\LoadedRelationNames;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Authorization\PreparedRows;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\EncryptedListDocument;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\PreparedDocument;
use Orchestra\Testbench\Attributes\WithConfig;
use Orchestra\Testbench\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('app.key', 'base64:MDEyMzQ1Njc4OWFiY2RlZjAxMjM0NTY3ODlhYmNkZWY=')]
class EncryptedPreparedRowsTest extends TestCase
{
    private const string EXTENSION = 'Vendor\Acl\AccessControlExtension';

    private const string PREVIOUS_KEY = 'ZmVkY2JhOTg3NjU0MzIxMGZlZGNiYTk4NzY1NDMyMTA=';

    /**
     * @param  class-string<Model>  $document
     * @param  list<string>  $previousKeys
     */
    #[Test]
    #[DataProvider('keyedDocuments')]
    public function it_keeps_a_row_whose_encrypted_cast_attribute_is_read(string $document, array $previousKeys): void
    {
        $checks = $this->checks($document, $previousKeys);
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $secret = $this->secret($checks);

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertSame(['a' => 1], $secret->toArray());
    }

    /**
     * @param  class-string<Model>  $document
     * @param  list<string>  $previousKeys
     */
    #[Test]
    #[DataProvider('keyedDocuments')]
    public function it_keeps_a_row_read_before_and_during_the_preparation(string $document, array $previousKeys): void
    {
        $checks = $this->checks($document, $previousKeys);
        $this->secret($checks);
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $secret = $this->secret($checks);

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertSame(['a' => 1], $secret->toArray());
    }

    /**
     * @param  class-string<Model>  $document
     * @param  list<string>  $previousKeys
     */
    #[Test]
    #[DataProvider('keyedDocuments')]
    public function it_refuses_a_row_whose_encrypted_cast_attribute_changes_in_place(string $document, array $previousKeys): void
    {
        $checks = $this->checks($document, $previousKeys);
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $this->secret($checks)['a'] = 2;

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    /** @param class-string<Model> $document */
    #[Test]
    #[DataProvider('encryptedDocuments')]
    public function it_keeps_a_row_encrypted_with_a_previous_key_once_read(string $document): void
    {
        $previous = (new Encrypter((string) base64_decode(self::PREVIOUS_KEY), 'AES-256-CBC'))->encryptString('{"a": 1}');
        $checks = $this->checks($document, ['base64:'.self::PREVIOUS_KEY], $previous);
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $secret = $this->secret($checks);

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertSame(['a' => 1], $secret->toArray());
    }

    /** @param class-string<Model> $document */
    #[Test]
    #[DataProvider('encryptedDocuments')]
    public function it_refuses_an_encrypted_value_of_another_type_with_previous_keys(string $document): void
    {
        $checks = $this->checks($document, ['base64:'.self::PREVIOUS_KEY]);
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $checks->rows->firstOrFail()->setAttribute('secret', ['a' => '1']);

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    #[Test]
    public function it_keeps_a_row_that_the_hook_read_for_an_extension_that_does_nothing(): void
    {
        $checks = $this->checks(PreparedDocument::class, []);
        $start = LoadedRelationNames::of($checks->rows);
        $checks->rows->firstOrFail()->getAttribute('secret');

        $rows = PreparedRows::of($checks->rows, $start);

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertSame(['a' => 1], $checks->rows->firstOrFail()->getAttribute('secret')->getArrayCopy());
    }

    #[Test]
    public function it_refuses_a_row_whose_encrypted_cast_attribute_changes_after_the_hook_read_it(): void
    {
        $checks = $this->checks(PreparedDocument::class, []);
        $start = LoadedRelationNames::of($checks->rows);
        $checks->rows->firstOrFail()->getAttribute('secret');
        $rows = PreparedRows::of($checks->rows, $start);

        $checks->rows->firstOrFail()->getAttribute('secret')['b'] = 2;

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    /** @return iterable<string, array{class-string<Model>}> */
    public static function encryptedDocuments(): iterable
    {
        yield 'an encrypted array object' => [PreparedDocument::class];
        yield 'an encrypted collection' => [EncryptedListDocument::class];
    }

    /** @return iterable<string, array{class-string<Model>, list<string>}> */
    public static function keyedDocuments(): iterable
    {
        foreach (self::encryptedDocuments() as $name => [$document]) {
            yield $name.' with the current key only' => [$document, []];
            yield $name.' with previous keys' => [$document, ['base64:'.self::PREVIOUS_KEY]];
        }
    }

    /** @return Arrayable<array-key, mixed>&ArrayAccess<array-key, mixed> */
    private function secret(PendingChecks $checks): mixed
    {
        $secret = $checks->rows->firstOrFail()->getAttribute('secret');

        if (! $secret instanceof Arrayable || ! $secret instanceof ArrayAccess) {
            $this->fail('The secret attribute is not read through its cast.');
        }

        return $secret;
    }

    /**
     * @param  class-string<Model>  $document
     * @param  list<string>  $previousKeys
     */
    private function checks(string $document, array $previousKeys, ?string $secret = null): PendingChecks
    {
        config(['app.previous_keys' => $previousKeys]);
        $this->app?->forgetInstance('encrypter');
        Crypt::clearResolvedInstance('encrypter');
        $row = (new $document)->newFromBuilder(['id' => 1, 'secret' => $secret ?? Crypt::encryptString('{"a": 1}')]);

        return new PendingChecks(new TicketResource, 'view', new Collection([$row]), null);
    }
}
