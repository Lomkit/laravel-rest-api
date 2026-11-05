<?php

namespace Lomkit\Rest\Tests\Unit\Validation;

use Closure;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Config\Repository as ConfigRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\Field;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ExistingKey;
use Lomkit\Rest\Validation\KeyList;
use Lomkit\Rest\Validation\KeysGrammar;
use Lomkit\Rest\Validation\Rejected;
use Lomkit\Rest\Validation\Violation;
use Lomkit\Rest\Validation\Violations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class KeysGrammarTest extends TestCase
{
    private Violations $violations;

    /**
     * @param  list<Field>  $fields
     * @param  list<int|string>  $keys
     */
    #[Test]
    #[DataProvider('keys')]
    public function it_types_the_keys_after_the_id_field(string $keyType, array $fields, int|string $key, string $rule, array $keys): void
    {
        $list = $this->parse([$key], $keyType, $fields);

        $this->assertSame($rule, $list->rules['resources.0'][0]);
        $this->assertInstanceOf(ExistingKey::class, $list->rules['resources.0'][1]);
        $this->assertCount(2, $list->rules['resources.0']);
        $this->assertSame($keys, $list->keys);
    }

    /** @return array<string, array{string, list<Field>, int|string, string, list<int|string>}> */
    public static function keys(): array
    {
        $uuid = '019A35F0-7C2E-7A41-9B3D-5E8F1C2A4B60';
        $ulid = '01ARZ3NDEKTSV4RRFFQ69G5FAV';

        return [
            'an integer key' => ['int', [ID::make('id')], 5, 'integer', [5]],
            'a string key' => ['string', [ID::make('id')], 'abc', 'string', ['abc']],
            'a uuid declared on the id field' => ['string', [ID::make('id')->keyType('uuid')], $uuid, 'uuid', [$uuid]],
            'a ulid declared on the id field' => ['string', [ID::make('id')->keyType('ulid')], $ulid, 'ulid', [$ulid]],
            'an integer key without an id field' => ['int', [Text::make('title')], 5, 'integer', [5]],
            'a string key without an id field' => ['string', [Text::make('title')], 'abc', 'string', ['abc']],
        ];
    }

    #[Test]
    public function it_casts_integer_keys(): void
    {
        $this->assertSame([5, 6], $this->parse(['5', 6])->keys);
    }

    #[Test]
    public function it_keeps_string_keys_as_strings(): void
    {
        $this->assertSame(['5', '05'], $this->parse(['5', '05'], 'string')->keys);
    }

    #[Test]
    public function it_checks_no_value_of_another_type(): void
    {
        $list = $this->parse(['one']);

        $this->assertSame(['integer'], $list->rules['resources.0']);
        $this->assertSame([], $list->keys);
        $this->assertTrue($this->violations->isEmpty());
    }

    #[Test]
    public function it_reports_repeated_keys(): void
    {
        $list = $this->parse([5, '5', 6]);

        $this->assertEquals([Violation::repeated('resources.0'), Violation::repeated('resources.1')], $this->recorded());
        $this->assertSame(['integer'], $list->rules['resources.0']);
        $this->assertSame(['integer'], $list->rules['resources.1']);
        $this->assertInstanceOf(ExistingKey::class, $list->rules['resources.2'][array_key_last($list->rules['resources.2'])]);
        $this->assertSame([5, 6], $list->keys);

        $this->parse(['019A35F0-7C2E-7A41-9B3D-5E8F1C2A4B60', '019a35f0-7c2e-7a41-9b3d-5e8f1c2a4b60'], 'string', [ID::make('id')->keyType('uuid')]);

        $this->assertEquals([Violation::repeated('resources.0'), Violation::repeated('resources.1')], $this->recorded());
    }

    #[Test]
    public function it_reports_an_object_of_keys(): void
    {
        $list = $this->parse(['a' => 5]);

        $this->assertEquals([Violation::notList('resources')], $this->recorded());
        $this->assertSame([], $list->rules);
    }

    #[Test]
    public function it_reports_more_keys_than_allowed(): void
    {
        $this->parse([1, 2, 3], max: 2);

        $this->assertEquals([Violation::tooMany('resources', 2)], $this->recorded());
    }

    #[Test]
    public function it_runs_no_query_while_parsing(): void
    {
        $this->parse([5, 6], query: fn (): Builder => $this->fail('The keys were looked up while parsing.'));

        $this->assertTrue($this->violations->isEmpty());
    }

    /**
     * @param  array<array-key, mixed>  $resources
     * @param  list<Field>  $fields
     * @param  (Closure(): Builder<Model>)|null  $query
     */
    private function parse(array $resources, string $keyType = 'int', array $fields = [], int $max = 10, ?Closure $query = null): KeyList
    {
        $this->violations = new Violations;
        $resource = self::resource($keyType, $fields === [] ? [ID::make('id')] : $fields);

        return KeysGrammar::of($resource, $query ?? fn (): Builder => $resource::newModel()->newQuery(), $max)
            ->parse(Cursor::of(['resources' => $resources], $this->violations)->at('resources'));
    }

    /** @return list<Violation> */
    private function recorded(): array
    {
        $recorded = [];

        foreach ($this->violations->applyTo([]) as $rules) {
            foreach ($rules as $rule) {
                $this->assertInstanceOf(Rejected::class, $rule);
                $recorded[] = $rule->violation;
            }
        }

        return $recorded;
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
            public function fields(): array
            {
                return $this->declared;
            }
        };

        $resource::$model = $model::class;
        $container = new Container;
        $container->instance(ConfigRepository::class, new Repository(['rest' => require __DIR__.'/../../../config/rest.php']));

        return $resource->withinContext(new RestContext(new RestRequest, $container));
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
