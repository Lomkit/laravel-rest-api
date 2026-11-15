<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Illuminate\Database\Eloquent\Collection;
use Lomkit\Rest\Authorization\PendingChecks;
use Lomkit\Rest\Authorization\PreparedRows;
use Lomkit\Rest\Exceptions\InvalidAuthorizationExtensionException;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketResource;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\PreparedDocument;
use Lomkit\Rest\Tests\Unit\Authorization\Stubs\Settings;
use PHPUnit\Framework\Attributes\DataProvider;
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

    #[Test]
    public function it_keeps_a_row_whose_object_cast_attribute_is_read_and_stored_again(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows);
        $row = $checks->rows->firstOrFail();

        $settings = $row->getAttribute('settings');
        $row->getAttribute('meta');
        $row->getAttributes();

        $rows->ensureUnchanged('acl', $checks);
        $this->assertInstanceOf(Settings::class, $settings);
        $this->assertSame(['a' => 1], $settings->values);
        $this->assertSame('{"a":1}', $row->getAttributes()['settings']);
    }

    #[Test]
    public function it_refuses_a_row_whose_object_cast_attribute_changes_in_place(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows);

        $settings = $checks->rows->firstOrFail()->getAttribute('settings');
        $this->assertInstanceOf(Settings::class, $settings);
        $settings->values['a'] = 2;

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', $checks);
    }

    #[Test]
    public function it_refuses_a_row_whose_object_cast_attribute_is_replaced(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows);

        $checks->rows->firstOrFail()->setAttribute('settings', new Settings(['a' => 2]));

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', $checks);
    }

    /** @return iterable<string, array{mixed}> */
    public static function equalValues(): iterable
    {
        yield 'a numeric string' => ['3'];
        yield 'a float' => [3.0];
    }

    #[Test]
    #[DataProvider('equalValues')]
    public function it_refuses_a_row_whose_attribute_takes_a_value_of_another_type(mixed $value): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows);

        $checks->rows->firstOrFail()->setAttribute('team_id', $value);

        $this->assertFalse($checks->rows->firstOrFail()->isDirty());
        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', $checks);
    }

    #[Test]
    public function it_lets_an_extension_replace_a_relation_that_the_preparation_loaded(): void
    {
        $checks = $this->checks();
        $row = $checks->rows->firstOrFail();
        $start = PreparedRows::of($checks->rows);
        $row->setRelation('tags', new Collection);

        $rows = PreparedRows::of($checks->rows, $start);
        $row->setRelation('tags', new Collection);

        $rows->ensureUnchanged('acl', $checks);
        $this->assertTrue($row->relationLoaded('tags'));
    }

    #[Test]
    public function it_refuses_an_extension_that_replaces_a_relation_loaded_before_the_preparation(): void
    {
        $checks = $this->checks();
        $row = $checks->rows->firstOrFail();
        $row->setRelation('team', null);
        $start = PreparedRows::of($checks->rows);
        $row->setRelation('tags', new Collection);

        $rows = PreparedRows::of($checks->rows, $start);
        $row->setRelation('team', new PreparedDocument);

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', $checks);
    }

    private function checks(): PendingChecks
    {
        $row = (new PreparedDocument)->newFromBuilder(['id' => 1, 'team_id' => 3, 'meta' => '{"a": 1}', 'settings' => '{"a": 1}']);

        return new PendingChecks(new TicketResource, 'view', new Collection([$row]), null);
    }
}
