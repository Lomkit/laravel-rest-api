<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Casts\AsEnumArrayObject;
use Illuminate\Database\Eloquent\Casts\AsFluent;
use Illuminate\Database\Eloquent\Casts\AsStringable;
use Illuminate\Database\Eloquent\Casts\AsVector;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;
use Illuminate\Support\Stringable;
use Lomkit\Rest\Fields\Json;
use Lomkit\Rest\Tests\Fixtures\Lab\EncodedJson;
use Lomkit\Rest\Tests\Fixtures\Lab\Instrument;
use Lomkit\Rest\Tests\Fixtures\Lab\Mounting;
use Lomkit\Rest\Tests\Fixtures\Lab\Recorder;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class JsonBindingTest extends TestCase
{
    #[Test]
    #[DataProvider('castsThatTakeTheValue')]
    public function it_leaves_the_encoding_and_the_decoding_to_a_cast_of_the_bound_model_that_takes_the_value(string $cast): void
    {
        $field = Json::make('specs');
        $field->bind($this->modelCasting($cast));
        $stringable = new Stringable('{"ram":8}');

        $this->assertSame(['ram' => 8], $field->toStorage(['ram' => 8]));
        $this->assertSame('{"ram":8}', $field->fromStorage('{"ram":8}'));
        $this->assertSame($stringable, $field->fromStorage($stringable));
    }

    /** @return array<string, array{string}> */
    public static function castsThatTakeTheValue(): array
    {
        return [
            'array' => ['array'],
            'json with its option' => ['json:unicode'],
            'encrypted object' => ['encrypted:object'],
            'collection class with its argument' => [AsCollection::class.':'.Collection::class],
            'enum array object class with its argument' => [AsEnumArrayObject::class.':'.PostStatus::class],
            'fluent class' => [AsFluent::class],
            'vector class' => [AsVector::class],
            'cast class of the app' => [EncodedJson::class],
        ];
    }

    #[Test]
    #[DataProvider('castsThatStoreAString')]
    public function it_encodes_and_decodes_the_value_when_the_bound_model_stores_it_as_a_string(?string $cast): void
    {
        $field = Json::make('specs');
        $field->bind($this->modelCasting($cast));

        $this->assertSame('{"ram":8,"ports":["usb"]}', $field->toStorage(['ram' => 8, 'ports' => ['usb']]));
        $this->assertSame(['ram' => 8, 'ports' => ['usb']], $field->fromStorage('{"ram":8,"ports":["usb"]}'));
        $this->assertSame(['ram' => 8], $field->fromStorage(['ram' => 8]));
        $this->assertNull($field->fromStorage('not json'));
        $this->assertNull($field->toStorage(null));
        $this->assertNull($field->fromStorage(null));
    }

    /** @return array<string, array{string|null}> */
    public static function castsThatStoreAString(): array
    {
        return [
            'no cast' => [null],
            'string' => ['string'],
            'encrypted string' => ['encrypted'],
            'stringable class' => [AsStringable::class],
        ];
    }

    #[Test]
    public function it_decodes_the_stringable_value_that_a_stringable_cast_of_the_bound_model_returns(): void
    {
        $field = Json::make('specs');
        $field->bind($this->modelCasting(AsStringable::class));

        $this->assertSame(['ram' => 8, 'ports' => ['usb']], $field->fromStorage(new Stringable('{"ram":8,"ports":["usb"]}')));
    }

    #[Test]
    #[DataProvider('modelsWithASetMutator')]
    public function it_leaves_the_encoding_and_the_decoding_to_a_set_mutator_of_the_bound_model(Model $model): void
    {
        $field = Json::make('notes');
        $field->bind($model);

        $this->assertSame(['done' => true], $field->toStorage(['done' => true]));
        $this->assertSame('{"done":true}', $field->fromStorage('{"done":true}'));
    }

    /** @return array<string, array{Model}> */
    public static function modelsWithASetMutator(): array
    {
        return [
            'set method' => [new class extends Model
            {
                public function setNotesAttribute(mixed $value): void
                {
                    $this->attributes['notes'] = json_encode($value, JSON_THROW_ON_ERROR);
                }
            }],
            'attribute with a set callback' => [new Recorder],
        ];
    }

    #[Test]
    public function it_encodes_the_value_when_the_bound_model_only_reads_it_through_an_accessor(): void
    {
        $field = Json::make('notes');
        $field->bind(new class extends Model
        {
            /** @return Attribute<mixed, never> */
            protected function notes(): Attribute
            {
                return Attribute::get(fn (?string $value): mixed => $value === null ? null : json_decode($value, true, flags: JSON_THROW_ON_ERROR));
            }
        });

        $this->assertSame('{"done":true}', $field->toStorage(['done' => true]));
    }

    #[Test]
    public function it_reads_the_casts_of_the_pivot_class_each_copy_is_bound_to(): void
    {
        $field = Json::make('wiring');

        $defaultPivot = $field->boundTo(new Pivot);
        $castingPivot = $field->boundTo(new Mounting);

        $this->assertSame('{"pins":[1]}', $defaultPivot->toStorage(['pins' => [1]]));
        $this->assertSame(['pins' => [1]], $castingPivot->toStorage(['pins' => [1]]));
        $this->assertSame(['pins' => [1]], $field->toStorage(['pins' => [1]]));
    }

    #[Test]
    public function it_leaves_the_value_unchanged_until_it_is_bound(): void
    {
        $field = Json::make('specs');

        $this->assertSame(['ram' => 8], $field->toStorage(['ram' => 8]));
        $this->assertSame('{"ram":8}', $field->fromStorage('{"ram":8}'));
    }

    #[Test]
    public function it_fills_the_encoded_value_on_a_column_the_bound_model_does_not_cast(): void
    {
        $scope = new Instrument;
        $specs = Json::make('specs');
        $calibration = Json::make('calibration');
        $specs->bind($scope);
        $calibration->bind($scope);

        $specs->fill($scope, ['ram' => 8]);
        $calibration->fill($scope, ['gain' => 2]);

        $this->assertSame(['specs' => '{"ram":8}', 'calibration' => '{"gain":2}'], $scope->getAttributes());
        $this->assertSame(['gain' => 2], $scope->calibration);
    }

    #[Test]
    public function it_hands_the_value_as_sent_to_its_fill_callback(): void
    {
        $scope = new Instrument;
        $field = Json::make('specs')->fillUsing(function (Model $model, mixed $value): void {
            $model->forceFill(['name' => $value]);
        });
        $field->bind($scope);

        $field->fill($scope, ['ram' => 8]);

        $this->assertSame(['name' => ['ram' => 8]], $scope->getAttributes());
    }

    private function modelCasting(?string $cast): Model
    {
        return new class($cast === null ? [] : ['specs' => $cast]) extends Model
        {
            /** @param array<string, string> $declaredCasts */
            public function __construct(private readonly array $declaredCasts = [])
            {
                parent::__construct();
            }

            /** @return array<string, string> */
            #[\Override]
            protected function casts(): array
            {
                return $this->declaredCasts;
            }
        };
    }
}
