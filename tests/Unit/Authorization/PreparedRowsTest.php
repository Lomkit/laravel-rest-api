<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Closure;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\LoadedRelationNames;
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
    private const string EXTENSION = 'Vendor\Acl\AccessControlExtension';

    /** @param Closure(Model): mixed $change */
    #[Test]
    #[DataProvider('settingChanges')]
    public function it_refuses_a_row_whose_write_settings_change(Closure $change): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $change($checks->rows->firstOrFail());

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    /** @return iterable<string, array{Closure(Model): mixed}> */
    public static function settingChanges(): iterable
    {
        yield 'whether it exists' => [static function (Model $row): void {
            $row->exists = false;
        }];
        yield 'its table' => [static fn (Model $row): Model => $row->setTable('teams')];
        yield 'its connection' => [static fn (Model $row): Model => $row->setConnection('other')];
        yield 'its key name' => [static fn (Model $row): Model => $row->setKeyName('team_id')];
        yield 'its casts' => [static fn (Model $row): Model => $row->mergeCasts(['team_id' => 'string'])];
        yield 'its timestamps' => [static function (Model $row): void {
            $row->timestamps = false;
        }];
    }

    /** @param Closure(PendingChecks): mixed $change */
    #[Test]
    #[DataProvider('listChanges')]
    public function it_refuses_a_change_to_the_list_of_rows_or_to_a_row_kept_as_original(Closure $change): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $change($checks);

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    /** @return iterable<string, array{Closure(PendingChecks): mixed}> */
    public static function listChanges(): iterable
    {
        yield 'an attribute kept as original' => [static fn (PendingChecks $checks): Model => $checks->rows->firstOrFail()->setAttribute('team_id', 4)->syncOriginal()];
        yield 'the list of rows' => [static fn (PendingChecks $checks): ?Model => $checks->rows->pop()];
        yield 'a row of the list' => [static fn (PendingChecks $checks): Collection => $checks->rows->put(0, new PreparedDocument)];
    }

    /** @param Closure(Model): mixed $change */
    #[Test]
    #[DataProvider('relatedChanges')]
    public function it_refuses_a_change_to_the_models_of_a_relation_loaded_before_the_preparation(Closure $change): void
    {
        $checks = $this->checks();
        $row = $checks->rows->firstOrFail();
        $row->setRelation('links', new Collection([$this->related(2), $this->related(3)]));
        $row->getRelation('links')->firstOrFail()->setRelation('links', new Collection([$this->related(4)]));
        $start = LoadedRelationNames::of($checks->rows);
        $rows = PreparedRows::of($checks->rows, $start);

        $change($row);

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    /** @return iterable<string, array{Closure(Model): mixed}> */
    public static function relatedChanges(): iterable
    {
        yield 'a model pushed' => [static fn (Model $row): mixed => $row->getRelation('links')->push(new PreparedDocument)];
        yield 'a model removed' => [static fn (Model $row): mixed => $row->getRelation('links')->forget(1)];
        yield 'a model put in place of another' => [static fn (Model $row): mixed => $row->getRelation('links')->put(1, new PreparedDocument)];
        yield 'the models reordered' => [static fn (Model $row): mixed => $row->setRelation('links', $row->getRelation('links')->reverse())];
        yield 'a model pushed into a nested relation' => [static fn (Model $row): mixed => $row->getRelation('links')->firstOrFail()->getRelation('links')->push(new PreparedDocument)];
        yield 'a nested relation replaced' => [static fn (Model $row): mixed => $row->getRelation('links')->firstOrFail()->setRelation('links', new Collection)];
        yield 'a nested relation removed' => [static fn (Model $row): mixed => $row->getRelation('links')->firstOrFail()->unsetRelation('links')];
    }

    #[Test]
    public function it_lets_an_extension_load_a_relation_of_a_related_model(): void
    {
        $checks = $this->checks();
        $row = $checks->rows->firstOrFail();
        $row->setRelation('links', new Collection([$this->related(2)]));
        $start = LoadedRelationNames::of($checks->rows);
        $row->getRelation('links')->firstOrFail()->setRelation('team', $this->related(5));
        $rows = PreparedRows::of($checks->rows, $start);

        $row->getRelation('links')->firstOrFail()->setRelation('team', $this->related(6));
        $row->getRelation('links')->firstOrFail()->setRelation('owner', $this->related(7));

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertSame(6, $row->getRelation('links')->firstOrFail()->getRelation('team')->getKey());
    }

    #[Test]
    public function it_keeps_a_row_whose_related_models_point_back_to_it(): void
    {
        $checks = $this->checks();
        $row = $checks->rows->firstOrFail();
        $child = $this->related(2);
        $row->setRelation('links', new Collection([$child]));
        $child->setRelation('parent', $row);
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $child->getRelation('parent');

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertSame($row, $child->getRelation('parent'));
    }

    #[Test]
    public function it_keeps_a_row_whose_cast_attribute_is_read(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $meta = $checks->rows->firstOrFail()->getAttribute('meta');

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertSame(['a' => 1], $meta->getArrayCopy());
    }

    #[Test]
    public function it_refuses_a_row_whose_cast_attribute_changes(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $checks->rows->firstOrFail()->getAttribute('meta')['a'] = 2;

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    #[Test]
    public function it_names_the_extension_that_changed_the_rows(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));
        $checks->rows->firstOrFail()->setAttribute('id', 2);

        try {
            $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
            $this->fail('The change was not refused.');
        } catch (InvalidAuthorizationExtensionException $exception) {
            $this->assertSame([true, false], [$exception->concerns(self::EXTENSION), $exception->concerns('acl')]);
            $this->assertSame('The [acl] extension '.self::EXTENSION.' changed the rows that it prepares for the ability [view] of '.TicketResource::class.': an attribute, a relation loaded before the preparation or its models, the exists flag, table, connection, key name, casts or timestamps of a row, or the list of rows. The rows are the rendered models: read what the policies need into a store of the extension, keyed by user and row key, and leave the rows as they are.', $exception->getMessage());
        }
    }

    #[Test]
    public function it_keeps_a_row_whose_object_cast_attribute_is_read_and_stored_again(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));
        $row = $checks->rows->firstOrFail();

        $settings = $row->getAttribute('settings');
        $row->getAttribute('meta');
        $row->getAttributes();

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertInstanceOf(Settings::class, $settings);
        $this->assertSame(['a' => 1], $settings->values);
        $this->assertSame('{"a":1}', $row->getAttributes()['settings']);
    }

    #[Test]
    public function it_refuses_a_row_whose_object_cast_attribute_changes_in_place(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $settings = $checks->rows->firstOrFail()->getAttribute('settings');
        $this->assertInstanceOf(Settings::class, $settings);
        $settings->values['a'] = 2;

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    #[Test]
    public function it_refuses_a_row_whose_object_cast_attribute_is_replaced(): void
    {
        $checks = $this->checks();
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $checks->rows->firstOrFail()->setAttribute('settings', new Settings(['a' => 2]));

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
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
        $rows = PreparedRows::of($checks->rows, LoadedRelationNames::of($checks->rows));

        $checks->rows->firstOrFail()->setAttribute('team_id', $value);

        $this->assertFalse($checks->rows->firstOrFail()->isDirty());
        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    #[Test]
    public function it_lets_an_extension_replace_a_relation_that_the_preparation_loaded(): void
    {
        $checks = $this->checks();
        $row = $checks->rows->firstOrFail();
        $start = LoadedRelationNames::of($checks->rows);
        $row->setRelation('tags', new Collection);

        $rows = PreparedRows::of($checks->rows, $start);
        $row->setRelation('tags', new Collection);

        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
        $this->assertTrue($row->relationLoaded('tags'));
    }

    #[Test]
    public function it_refuses_an_extension_that_replaces_a_relation_loaded_before_the_preparation(): void
    {
        $checks = $this->checks();
        $row = $checks->rows->firstOrFail();
        $row->setRelation('team', null);
        $start = LoadedRelationNames::of($checks->rows);
        $row->setRelation('tags', new Collection);

        $rows = PreparedRows::of($checks->rows, $start);
        $row->setRelation('team', new PreparedDocument);

        $this->expectExceptionObject(InvalidAuthorizationExtensionException::changedRows('acl', self::EXTENSION, TicketResource::class, 'view'));
        $rows->ensureUnchanged('acl', self::EXTENSION, $checks);
    }

    private function checks(): PendingChecks
    {
        $row = (new PreparedDocument)->newFromBuilder([
            'id' => 1,
            'team_id' => 3,
            'meta' => '{"a": 1}',
            'settings' => '{"a": 1}',
        ]);

        return new PendingChecks(new TicketResource, 'view', new Collection([$row]), null);
    }

    private function related(int $id): PreparedDocument
    {
        return (new PreparedDocument)->newFromBuilder(['id' => $id]);
    }
}
