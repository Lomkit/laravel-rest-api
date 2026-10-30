<?php

namespace Lomkit\Rest\Tests\Unit\Http;

use Illuminate\Container\Container;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\ForceDestroyRequest;
use Lomkit\Rest\Http\Requests\RestoreRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ResourceKeyRulesTest extends TestCase
{
    /**
     * @param  list<Field>  $fields
     * @param  list<string>  $rules
     */
    #[Test]
    #[DataProvider('keys')]
    public function it_types_the_keys_after_the_id_field(string $keyType, array $fields, array $rules): void
    {
        $resource = self::resource($keyType, $fields);

        $this->assertSame($rules, (new RestoreRequest)->restoreRules($resource)['resources.*']);
        $this->assertSame($rules, (new ForceDestroyRequest)->forceDestroyRules($resource)['resources.*']);
    }

    /** @return array<string, array{string, list<Field>, list<string>}> */
    public static function keys(): array
    {
        return [
            'an integer key' => ['int', [ID::make('id')], ['integer']],
            'a string key' => ['string', [ID::make('id')], ['string']],
            'a uuid declared on the id field' => ['string', [ID::make('id')->keyType('uuid')], ['uuid']],
            'a ulid declared on the id field' => ['string', [ID::make('id')->keyType('ulid')], ['ulid']],
            'an integer key without an id field' => ['int', [Text::make('title')], ['integer']],
            'a string key without an id field' => ['string', [Text::make('title')], ['string']],
        ];
    }

    /** @param list<Field> $fields */
    private static function resource(string $keyType, array $fields): Resource
    {
        $model = self::model($keyType);

        $resource = new class($fields) extends Resource
        {
            public static string $model;

            /** @param list<Field> $declared */
            public function __construct(private readonly array $declared) {}

            #[\Override]
            public function fields(RestRequest $request): array
            {
                return $this->declared;
            }
        };

        $resource::$model = $model::class;

        return $resource->withinContext(new RestContext(new RestRequest, new Container));
    }

    private static function model(string $keyType): Model
    {
        if ($keyType === 'int') {
            return new class extends Model {};
        }

        return new class extends Model
        {
            protected $keyType = 'string';
        };
    }
}
