<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\Rule;
use Lomkit\Rest\Exceptions\InvalidFieldException;
use Lomkit\Rest\Fields\Enum;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\Priority;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldBindingTest extends TestCase
{
    /** @return array<string, array{Model, string, list<string>}> */
    public static function keyedModels(): array
    {
        return [
            'integer key' => [self::integerKeyed(), 'id', ['integer']],
            'string key' => [new class extends Model
            {
                protected $keyType = 'string';
            }, 'id', ['string']],
            'string key declared by the table attribute' => [new #[Table(keyType: 'string')] class extends Model {}, 'id', ['string']],
            'uuid key' => [self::uuidKeyed(), 'id', ['uuid']],
            'integer key spelled integer' => [new class extends Model
            {
                protected $keyType = 'integer';
            }, 'id', ['integer']],
            'ulid key' => [new class extends Model
            {
                use HasUlids;
            }, 'id', ['ulid']],
            'uuid column beside an integer key' => [self::publicUuidModel(), 'public_id', ['uuid']],
            'integer key beside a uuid column' => [self::publicUuidModel(), 'id', ['integer']],
        ];
    }

    /** @return array<string, array{string, Model, list<string>}> */
    public static function declaredKeyTypes(): array
    {
        return [
            'int on a uuid model' => ['int', self::uuidKeyed(), ['integer']],
            'string on an integer model' => ['string', self::integerKeyed(), ['string']],
            'uuid on an integer model' => ['uuid', self::integerKeyed(), ['uuid']],
            'ulid on a uuid model' => ['ulid', self::uuidKeyed(), ['ulid']],
        ];
    }

    /** @return array<string, array{Enum}> */
    public static function enumsWithoutClass(): array
    {
        return [
            'unbound' => [Enum::make('status')],
            'attribute without cast' => [self::bound(Enum::make('title'), new Post)],
            'attribute cast to a scalar' => [self::bound(Enum::make('featured'), new Post)],
            'attribute cast to a date' => [self::bound(Enum::make('published_on'), new Post)],
        ];
    }

    /** @return array<string, array{class-string<\BackedEnum>, list<int|string>}> */
    public static function backedEnums(): array
    {
        return [
            'string backed' => [PostStatus::class, ['draft', 'review', 'published']],
            'int backed' => [Priority::class, [1, 2, 3]],
        ];
    }

    /** @param list<string> $rules */
    #[Test]
    #[DataProvider('keyedModels')]
    public function it_reads_the_key_type_of_an_id_from_the_bound_model(Model $model, string $name, array $rules): void
    {
        $this->assertSame($rules, self::bound(ID::make($name), $model)->baseRules());
    }

    #[Test]
    public function it_rejects_an_unknown_declared_key_type(): void
    {
        $this->expectExceptionObject(new InvalidFieldException('The ID field [id] has an unknown key type [integer]. Use int, string, uuid or ulid.'));

        ID::make('id')->keyType('integer');
    }

    #[Test]
    public function it_replaces_the_key_type_when_bound_again(): void
    {
        $field = self::bound(ID::make('id'), self::uuidKeyed());
        $field->bind(self::integerKeyed());

        $this->assertSame(['integer'], $field->baseRules());
    }

    /** @param list<string> $rules */
    #[Test]
    #[DataProvider('declaredKeyTypes')]
    public function it_prefers_the_declared_key_type_over_the_bound_model(string $keyType, Model $model, array $rules): void
    {
        $declaredFirst = self::bound(ID::make('id')->keyType($keyType), $model);
        $boundFirst = self::bound(ID::make('id'), $model)->keyType($keyType);

        $this->assertSame($rules, $declaredFirst->baseRules());
        $this->assertSame($rules, $boundFirst->baseRules());
    }

    #[Test]
    public function it_reads_the_enum_class_from_the_model_cast(): void
    {
        $field = self::bound(Enum::make('status'), new Post);

        $this->assertSame(PostStatus::class, $field->enumClass());
        $this->assertEquals([Rule::enum(PostStatus::class)], $field->baseRules());
        $this->assertSame(['draft', 'review', 'published'], $field->values());
    }

    #[Test]
    public function it_keeps_the_enum_class_given_to_make_over_the_model_cast(): void
    {
        $field = self::bound(Enum::make('status', Priority::class), new Post);

        $this->assertSame(Priority::class, $field->enumClass());
        $this->assertEquals([Rule::enum(Priority::class)], $field->baseRules());
    }

    #[Test]
    #[DataProvider('enumsWithoutClass')]
    public function it_requires_an_enum_class_to_build_the_base_rules(Enum $field): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessageMatches('/^The enum field \['.preg_quote($field->name, '/').'\] has no enum class\. Pass it to Enum::make\(\) or cast the attribute to an enum on the model\.$/');

        $field->baseRules();
    }

    #[Test]
    public function it_requires_an_enum_class_to_list_the_values(): void
    {
        $this->expectException(InvalidFieldException::class);
        $this->expectExceptionMessage('The enum field [state] has no enum class.');

        Enum::make('state')->values();
    }

    /** @param list<int|string> $values */
    #[Test]
    #[DataProvider('backedEnums')]
    public function it_lists_the_backed_values_of_the_enum_in_declaration_order(string $enum, array $values): void
    {
        $this->assertSame($values, Enum::make('level', $enum)->values());
    }

    private static function bound(ID|Enum $field, Model $model): ID|Enum
    {
        $field->bind($model);

        return $field;
    }

    private static function integerKeyed(): Model
    {
        return new class extends Model {};
    }

    private static function uuidKeyed(): Model
    {
        return new class extends Model
        {
            use HasUuids;
        };
    }

    private static function publicUuidModel(): Model
    {
        return new class extends Model
        {
            use HasUuids;

            #[\Override]
            public function uniqueIds(): array
            {
                return ['public_id'];
            }
        };
    }
}
