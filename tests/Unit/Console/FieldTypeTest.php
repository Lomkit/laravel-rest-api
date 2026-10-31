<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumCollection;
use Illuminate\Database\Eloquent\Casts\AsFluent;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Lomkit\Rest\Console\Generation\FieldType;
use Lomkit\Rest\Console\Generation\Schema\Column;
use Lomkit\Rest\Tests\Fixtures\Newsroom\ArticleStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class FieldTypeTest extends TestCase
{
    #[Test]
    #[DataProvider('casts')]
    public function it_reads_the_field_type_from_the_cast_of_the_model(mixed $cast, ?FieldType $type): void
    {
        $this->assertSame($type, FieldType::fromCast($cast));
    }

    /** @return array<string, array{mixed, FieldType|null}> */
    public static function casts(): array
    {
        return [
            'backed enum' => [ArticleStatus::class, FieldType::Enum],
            'array' => ['array', FieldType::Json],
            'encrypted array' => ['encrypted:array', FieldType::Json],
            'collection class' => [AsCollection::class, FieldType::Json],
            'enum collection with its argument' => [AsEnumCollection::class.':'.ArticleStatus::class, FieldType::Json],
            'fluent' => [AsFluent::class, FieldType::Json],
            'vector' => [AsVector::class, FieldType::Json],
            'boolean' => ['boolean', FieldType::Boolean],
            'integer' => ['int', FieldType::Integer],
            'decimal with its precision' => ['decimal:2', FieldType::Number],
            'date' => ['immutable_date', FieldType::Date],
            'datetime with its format' => ['datetime:Y-m-d H:i', FieldType::DateTime],
            'unix timestamp' => ['timestamp', null],
            'hashed' => ['hashed', null],
            'stringable' => [AsStringable::class, null],
            'no cast' => [null, null],
        ];
    }

    #[Test]
    #[DataProvider('columns')]
    public function it_reads_the_field_type_from_the_column_type(string $typeName, string $type, ?FieldType $fieldType): void
    {
        $this->assertSame($fieldType, FieldType::fromColumn(new Column('column', $typeName, $type, false, false, false, false)));
    }

    /** @return array<string, array{string, string, FieldType|null}> */
    public static function columns(): array
    {
        return [
            'mysql boolean' => ['tinyint', 'tinyint(1)', FieldType::Boolean],
            'mysql tiny integer' => ['tinyint', 'tinyint unsigned', FieldType::Integer],
            'pgsql boolean' => ['bool', 'boolean', FieldType::Boolean],
            'pgsql big integer' => ['int8', 'bigint', FieldType::Integer],
            'mysql year' => ['year', 'year', FieldType::Integer],
            'pgsql double' => ['float8', 'double precision', FieldType::Number],
            'sqlite decimal' => ['numeric', 'numeric', FieldType::Number],
            'date' => ['date', 'date', FieldType::Date],
            'pgsql timestamp' => ['timestamp', 'timestamp(0) without time zone', FieldType::DateTime],
            'pgsql timestamp with time zone' => ['timestamptz', 'timestamp(0) with time zone', FieldType::DateTime],
            'mysql json' => ['json', 'json', FieldType::Json],
            'pgsql jsonb' => ['jsonb', 'jsonb', FieldType::Json],
            'pgsql uuid' => ['uuid', 'uuid', FieldType::Text],
            'pgsql char' => ['bpchar', 'character(2)', FieldType::Text],
            'mysql enum' => ['enum', "enum('draft','published')", FieldType::Text],
            'time' => ['time', 'time', FieldType::Text],
            'binary' => ['bytea', 'bytea', null],
        ];
    }
}
