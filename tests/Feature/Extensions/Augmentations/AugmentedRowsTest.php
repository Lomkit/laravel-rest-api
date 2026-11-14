<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Augmentations;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Extensions\Contexts\AugmentationContext;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AliasFieldAuthorController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AliasFieldAuthorResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AugmentationLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstAugmentation;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FlagExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondAugmentation;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorController;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathAuthorResource;
use Lomkit\Rest\Tests\Feature\Search\Stubs\Paths\PathPostController;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;

class AugmentedRowsTest extends PackageTestCase
{
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        AugmentationLog::reset();
        Rest::resource('path-authors', PathAuthorController::class)->register();
        Rest::resource('path-posts', PathPostController::class)->register();
        DB::table('authors')->insert([
            ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-1'],
            ['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-2'],
        ]);
        DB::table('posts')->insert([
            ['id' => 1, 'author_id' => 1, 'title' => 'Notes', 'status' => 'published', 'views' => 1, 'published_on' => '2026-01-01'],
            ['id' => 2, 'author_id' => 1, 'title' => 'Hidden', 'status' => 'published', 'views' => 2, 'published_on' => '2026-01-01'],
            ['id' => 3, 'author_id' => 2, 'title' => 'Compilers', 'status' => 'published', 'views' => 3, 'published_on' => '2026-01-01'],
        ]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        AugmentationLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_adds_columns_subqueries_and_eager_loads_to_the_rendered_rows(): void
    {
        config(['rest.extensions' => ['flag' => FlagExtension::class]]);
        $this->captureQueries();

        $this->postJson('/path-authors/search', ['search' => ['selects' => [['field' => 'first_name']]]])->assertOk();

        $this->assertSame([[
            'select authors.first_name, authors.id, authors.api_token, (select \'seen\') as flag__marker, (select count(*) from posts where posts.author_id = authors.id) as flag__post_total from authors where (last_name != ?) and authors.deleted_at is null order by authors.id desc limit 50 offset 0',
            ['Hidden'],
        ]], $this->readsOf('authors'));
        $this->assertCount(1, $this->readsOf('drafts'));
    }

    /** @param array<string, mixed> $search */
    #[Test]
    #[DataProvider('searches')]
    public function it_renders_only_the_fields(bool $columns, array $search): void
    {
        config(['rest.search.select_columns' => $columns]);
        $plain = $this->postJson('/path-authors/search', ['search' => $search])->assertOk()->getContent();
        config(['rest.extensions' => ['flag' => FlagExtension::class]]);

        $augmented = $this->postJson('/path-authors/search', ['search' => $search])->assertOk()->getContent();

        $this->assertSame($plain, $augmented);
        $this->assertSame(['flag__marker', 'flag__post_total', 'flag__marker'], array_slice(AugmentationLog::$aliases, 0, 3));
    }

    /** @return array<string, array{bool, array<string, mixed>}> */
    public static function searches(): array
    {
        $search = ['includes' => [['relation' => 'posts']]];

        return [
            'selected columns' => [true, $search],
            'every column' => [false, $search],
            'selected fields' => [true, ['selects' => [['field' => 'id']], ...$search]],
        ];
    }

    #[Test]
    public function it_gives_the_gate_the_rows_that_carry_the_added_columns(): void
    {
        config(['rest.extensions' => ['flag' => FlagExtension::class]]);
        $seen = [];
        Gate::before(function (?Authenticatable $user, string $ability, array $arguments) use (&$seen): ?bool {
            $row = $arguments[0] ?? null;

            if ($row instanceof Model) {
                $seen[] = [$ability, class_basename($row), $row->getKey(), $row->getAttributes()['flag__marker'] ?? null, $row->getAttributes()['api_token'] ?? null];
            }

            return null;
        });

        $this->postJson('/path-authors/search', ['search' => [
            'gates' => ['update'],
            'includes' => [['relation' => 'posts', 'gates' => ['view']]],
        ]])->assertOk();

        $this->assertSame([
            ['update', 'Author', 2, 'seen', 'token-2'],
            ['view', 'Post', 3, 'seen', null],
            ['update', 'Author', 1, 'seen', 'token-1'],
            ['view', 'Post', 1, 'seen', null],
        ], $seen);
    }

    #[Test]
    public function it_gives_the_rendered_row_gates_of_each_level(): void
    {
        config(['rest.extensions' => ['flag' => FlagExtension::class]]);

        $this->postJson('/path-authors/search', ['search' => [
            'gates' => ['create', 'update'],
            'includes' => [['relation' => 'posts.author', 'gates' => ['view', 'delete']]],
        ]])->assertOk();

        $this->assertSame(['PathAuthorResource [update]', 'PathPostResource []', 'PathAuthorResource [view, delete]'], AugmentationLog::$gates);
    }

    #[Test]
    public function it_keeps_the_include_on_a_relation_name_clash(): void
    {
        config(['rest.extensions' => ['first' => FirstAugmentation::class]]);
        FirstAugmentation::$augment = static function (AugmentationContext $context): void {
            if ($context->resource instanceof PathAuthorResource) {
                $context->with('posts');
            }
        };
        $this->captureQueries();

        $response = $this->postJson('/path-authors/search', ['search' => ['includes' => [['relation' => 'posts']]]]);

        $response->assertOk()->assertJsonPath('data.*.posts.*.id', [3, 1]);
        $this->assertCount(1, $this->readsOf('posts'));
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_keeps_a_parent_constraint_of_a_nested_load_in_any_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        FirstAugmentation::$augment = static function (AugmentationContext $context): void {
            if ($context->resource instanceof PathAuthorResource) {
                $context->with('posts', static fn (Relation $posts): Relation => $posts->where('title', 'Notes'));
            }
        };
        SecondAugmentation::$augment = static function (AugmentationContext $context): void {
            if ($context->resource instanceof PathAuthorResource) {
                $context->with('posts.author');
            }
        };
        $this->captureQueries();

        $this->postJson('/path-authors/search', ['search' => []])->assertOk();

        $this->assertSame([['select * from posts where posts.author_id in (1, 2) and title = ?', ['Notes']]], $this->readsOf('posts'));
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function orders(): array
    {
        return [
            'the constraint first' => [['a' => FirstAugmentation::class, 'b' => SecondAugmentation::class]],
            'the nested load first' => [['a' => SecondAugmentation::class, 'b' => FirstAugmentation::class]],
        ];
    }

    #[Test]
    public function it_selects_the_parent_keys_of_an_eager_load(): void
    {
        config(['rest.extensions' => ['first' => FirstAugmentation::class]]);
        FirstAugmentation::$augment = static function (AugmentationContext $context): void {
            $context->with('author');
        };
        $this->captureQueries();

        $this->postJson('/path-posts/search', ['search' => ['selects' => [['field' => 'title']]]])->assertOk();

        $this->assertSame([
            ['select posts.title, posts.id, posts.author_id from posts where (title != ?) order by posts.id desc limit 50 offset 0', ['Hidden']],
        ], $this->readsOf('posts'));
        $this->assertSame([['select * from authors where authors.id in (1, 2) and authors.deleted_at is null', []]], $this->readsOf('authors'));
    }

    #[Test]
    #[DataProvider('invalidAliases')]
    public function it_refuses_an_invalid_alias(string $alias): void
    {
        $this->expectRefusal(static fn (AugmentationContext $context): string => $context->select($alias, Post::query()), InvalidQueryExtensionException::invalidAlias('first', $alias));
    }

    /** @return array<string, array{string}> */
    public static function invalidAliases(): array
    {
        return [
            'an empty alias' => [''],
            'an uppercase letter' => ['Total'],
            'a leading digit' => ['1total'],
            'a dash' => ['post-total'],
            'a dot' => ['post.total'],
            'more than 32 characters' => [str_repeat('a', 33)],
        ];
    }

    #[Test]
    public function it_refuses_an_alias_too_long_with_its_prefix(): void
    {
        $name = str_repeat('n', 32);
        config(['rest.extensions' => [$name => FirstAugmentation::class]]);
        FirstAugmentation::$augment = static function (AugmentationContext $context): void {
            AugmentationLog::$aliases[] = $context->select(str_repeat('a', 29), static function (\Illuminate\Database\Query\Builder $query): void {
                $query->selectRaw('1');
            });
            $context->select(str_repeat('b', 30), static function (\Illuminate\Database\Query\Builder $query): void {
                $query->selectRaw('1');
            });
        };
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::aliasTooLong($name, $name.'__'.str_repeat('b', 30), PathAuthorResource::class));

        try {
            $this->postJson('/path-authors/search', ['search' => []]);
        } finally {
            $this->assertSame([$name.'__'.str_repeat('a', 29)], AugmentationLog::$aliases);
        }
    }

    #[Test]
    public function it_refuses_an_alias_that_one_extension_selects_twice(): void
    {
        $this->expectRefusal(
            static fn (AugmentationContext $context): array => [$context->select('total', Post::query()->selectRaw('1')), $context->select('total', Post::query()->selectRaw('2'))],
            InvalidQueryExtensionException::aliasTaken('first__total', PathAuthorResource::class, ['first', 'first']),
        );
    }

    #[Test]
    public function it_refuses_an_alias_that_two_extension_names_reach(): void
    {
        config(['rest.extensions' => ['a__b' => FirstAugmentation::class, 'a' => SecondAugmentation::class]]);
        FirstAugmentation::$augment = static fn (AugmentationContext $context): string => $context->select('c', Post::query()->selectRaw('1'));
        SecondAugmentation::$augment = static fn (AugmentationContext $context): string => $context->select('b__c', Post::query()->selectRaw('2'));
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::aliasTaken('a__b__c', PathAuthorResource::class, ['a', 'a__b']));

        $this->postJson('/path-authors/search', ['search' => []]);
    }

    #[Test]
    public function it_refuses_an_alias_that_names_a_field(): void
    {
        Rest::resource('alias-field-authors', AliasFieldAuthorController::class)->register();
        config(['rest.extensions' => ['first' => FirstAugmentation::class]]);
        FirstAugmentation::$augment = static fn (AugmentationContext $context): string => $context->select('total', Post::query()->selectRaw('1'));
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::aliasNamesField('first', 'first__total', AliasFieldAuthorResource::class));

        $this->postJson('/alias-field-authors/search', ['search' => []]);
    }

    #[Test]
    public function it_refuses_an_unknown_relation(): void
    {
        $this->expectRefusal(static fn (AugmentationContext $context): mixed => $context->with('reviewers'), InvalidQueryExtensionException::unknownRelation('first', 'reviewers', PathAuthorResource::class));
    }

    #[Test]
    public function it_refuses_a_relation_loaded_twice(): void
    {
        config(['rest.extensions' => ['b' => SecondAugmentation::class, 'a' => FirstAugmentation::class]]);
        FirstAugmentation::$augment = static fn (AugmentationContext $context): mixed => $context->with('drafts');
        SecondAugmentation::$augment = static fn (AugmentationContext $context): mixed => $context->with('drafts');
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::relationTaken('drafts', PathAuthorResource::class, ['a', 'b']));

        $this->postJson('/path-authors/search', ['search' => []]);
    }

    #[Test]
    #[DataProvider('invalidColumns')]
    public function it_refuses_an_invalid_column(string $column): void
    {
        $this->expectRefusal(static fn (AugmentationContext $context): mixed => $context->column($column), InvalidQueryExtensionException::invalidColumn('first', $column, PathAuthorResource::class));
    }

    /** @return array<string, array{string}> */
    public static function invalidColumns(): array
    {
        return [
            'a qualified column' => ['authors.api_token'],
            'every column' => ['*'],
            'an expression' => ['count(*)'],
            'an alias' => ['api_token as token'],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('pairs')]
    public function it_gives_the_same_rows_and_sql_in_any_extension_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        FirstAugmentation::$augment = static function (AugmentationContext $context): void {
            $context->column('api_token');
            $context->select('total', Post::query()->selectRaw('count(*)')->whereColumn('posts.author_id', 'authors.id'));
        };
        SecondAugmentation::$augment = static function (AugmentationContext $context): void {
            $context->column('settings');
            $context->select('total', Post::query()->selectRaw('max(views)')->whereColumn('posts.author_id', 'authors.id'));
        };
        $this->captureQueries();

        $response = $this->postJson('/path-authors/search', ['search' => ['selects' => [['field' => 'first_name']]]]);

        $response->assertOk()->assertExactJson(['current_page' => 1, 'data' => [['first_name' => 'Grace'], ['first_name' => 'Ada']], 'from' => 1, 'last_page' => 1, 'per_page' => 50, 'to' => 2, 'total' => 2, 'meta' => []]);
        $this->assertSame([[
            'select authors.first_name, authors.id, authors.api_token, authors.settings, (select count(*) from posts where posts.author_id = authors.id) as a__total, (select max(views) from posts where posts.author_id = authors.id) as b__total from authors where (last_name != ?) and authors.deleted_at is null order by authors.id desc limit 50 offset 0',
            ['Hidden'],
        ]], $this->readsOf('authors'));
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function pairs(): array
    {
        return [
            'in name order' => [['a' => FirstAugmentation::class, 'b' => SecondAugmentation::class]],
            'in reverse order' => [['b' => SecondAugmentation::class, 'a' => FirstAugmentation::class]],
        ];
    }

    #[Test]
    public function it_exposes_no_query_builder_to_an_augmentation(): void
    {
        $class = new ReflectionClass(AugmentationContext::class);
        $methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), $class->getMethods(ReflectionMethod::IS_PUBLIC));
        $properties = array_map(static fn (ReflectionProperty $property): string => $property->getName().' '.($property->getType() instanceof ReflectionNamedType ? $property->getType()->getName() : ''), $class->getProperties(ReflectionProperty::IS_PUBLIC));

        $this->assertSame(['__construct', 'column', 'select', 'with'], $methods);
        $this->assertSame(['resource '.\Lomkit\Rest\Http\Resource::class, 'user '.Authenticatable::class, 'gates array'], $properties);
    }

    private function expectRefusal(\Closure $augment, InvalidQueryExtensionException $exception): void
    {
        config(['rest.extensions' => ['first' => FirstAugmentation::class]]);
        FirstAugmentation::$augment = $augment;
        $this->withoutExceptionHandling();

        $this->expectExceptionObject($exception);

        $this->postJson('/path-authors/search', ['search' => []]);
    }
}
