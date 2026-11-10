<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Contracts\PivotRelation;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\Relation;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Comment;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Desk;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Keyword;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Newsroom;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Photo;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Reporter;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Story;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Summary;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Topic;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class GeneratedRelationsTest extends ScaffoldingTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    /** @param  array<string, class-string>  $related */
    #[Test]
    #[DataProvider('models')]
    public function it_generates_the_relations_whose_related_model_has_a_resource(string $name, string $model, array $related): void
    {
        foreach ($related as $resource => $relatedModel) {
            $this->artisan('rest:resource', ['name' => $resource, '--model' => $relatedModel, '--no-interaction' => true])->assertSuccessful();
        }

        $this->artisan('rest:resource', ['name' => $name, '--model' => $model, '--generate' => true, '--no-interaction' => true])->assertSuccessful();

        $code = (string) file_get_contents(app_path("Rest/Resources/{$name}.php"));
        $this->assertStringEqualsFile(__DIR__.'/Golden/'.DB::connection()->getDriverName()."/{$name}.golden", $code);

        $resource = "App\\Rest\\Resources\\{$name}";
        $this->loadGeneratedClass($resource);
        $this->assertInstanceOf(Resource::class, $instance = new $resource);
        $relations = $instance->relations();
        $pivotFields = array_sum(array_map(
            fn (Relation $relation): int => $relation instanceof PivotRelation ? count($relation->getPivotFields()) : 0,
            $relations,
        ));
        $this->assertSame(
            substr_count($code, '::make('),
            count(FieldSet::of($instance->fields())) + count($relations) + $pivotFields,
        );
    }

    #[Test]
    public function it_aliases_a_related_resource_whose_short_name_another_related_resource_takes(): void
    {
        $this->writeModel('App\Models\Contributor');
        $this->writeModel('App\Models\Columnist');
        $this->writeModel('App\Models\Masthead', "public function contributor(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo { return \$this->belongsTo(Contributor::class); }\n\n    public function columnist(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo { return \$this->belongsTo(Columnist::class); }");
        foreach (['contributors', 'columnists'] as $name) {
            Schema::create($name, function (Blueprint $table): void {
                $table->id();
            });
        }
        Schema::create('mastheads', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contributor_id')->constrained();
            $table->foreignId('columnist_id')->constrained();
        });
        $this->artisan('rest:resource', ['name' => 'CreditResource', '--model' => 'Contributor', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:resource', ['name' => 'Admin/CreditResource', '--model' => 'Columnist', '--no-interaction' => true])->assertSuccessful();

        $this->artisan('rest:resource', ['name' => 'MastheadResource', '--model' => 'Masthead', '--generate' => true, '--no-interaction' => true])->assertSuccessful();

        $code = (string) file_get_contents(app_path('Rest/Resources/MastheadResource.php'));
        $this->assertStringContainsString("use App\\Models\\Masthead;\nuse App\\Rest\\Resources\\Admin\\CreditResource as CreditResourceResource;\n", $code);
        $this->assertStringContainsString(
            "        return [\n"
            ."            BelongsTo::make('contributor', CreditResource::class)\n"
            ."                ->requiredOnCreation()\n"
            ."                ->withoutOperations(MutationOperation::Detach),\n"
            ."            BelongsTo::make('columnist', CreditResourceResource::class)\n"
            ."                ->requiredOnCreation()\n"
            ."                ->withoutOperations(MutationOperation::Detach),\n"
            ."        ];\n",
            $code,
        );
        $this->loadGeneratedClass('App\Rest\Resources\MastheadResource');
    }

    #[Test]
    public function it_withdraws_detach_where_the_foreign_key_or_the_morph_type_does_not_accept_null(): void
    {
        $this->writeModel('App\Models\Badge');
        $this->writeModel('App\Models\Shift');
        $this->writeModel('App\Models\Memo');
        $this->writeModel('App\Models\Crew', "public function badge(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo { return \$this->belongsTo(Badge::class); }\n\n    public function shifts(): \\Illuminate\\Database\\Eloquent\\Relations\\HasMany { return \$this->hasMany(Shift::class); }\n\n    public function memos(): \\Illuminate\\Database\\Eloquent\\Relations\\MorphMany { return \$this->morphMany(Memo::class, 'memoable'); }");
        Schema::create('badges', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('crews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('badge_id')->default(1);
        });
        Schema::create('shifts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('crew_id')->nullable();
        });
        Schema::create('memos', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('memoable_id')->nullable();
            $table->string('memoable_type');
        });
        foreach (['Badge', 'Shift', 'Memo'] as $model) {
            $this->artisan('rest:resource', ['name' => "{$model}Resource", '--model' => $model, '--no-interaction' => true])->assertSuccessful();
        }

        $this->artisan('rest:resource', ['name' => 'CrewResource', '--model' => 'Crew', '--generate' => true, '--no-interaction' => true])->assertSuccessful();

        $code = (string) file_get_contents(app_path('Rest/Resources/CrewResource.php'));
        $this->assertStringContainsString("use Lomkit\\Rest\\Fields\\MutationOperation;\n", $code);
        $this->assertStringContainsString(
            "        return [\n"
            ."            BelongsTo::make('badge', BadgeResource::class)->withoutOperations(MutationOperation::Detach),\n"
            ."            HasMany::make('shifts', ShiftResource::class),\n"
            ."            MorphMany::make('memos', MemoResource::class)->withoutOperations(MutationOperation::Detach),\n"
            ."        ];\n",
            $code,
        );
        $this->loadGeneratedClass('App\Rest\Resources\CrewResource');
    }

    #[Test]
    public function it_aliases_a_related_resource_named_like_the_operation_enum(): void
    {
        $this->writeModel('App\Models\Permit');
        $this->writeModel('App\Models\Driver', "public function permit(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo { return \$this->belongsTo(Permit::class); }");
        Schema::create('permits', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('drivers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('permit_id')->constrained();
        });
        $this->artisan('rest:resource', ['name' => 'MutationOperation', '--model' => 'Permit', '--no-interaction' => true])->assertSuccessful();

        $this->artisan('rest:resource', ['name' => 'DriverResource', '--model' => 'Driver', '--generate' => true, '--no-interaction' => true])->assertSuccessful();

        $code = (string) file_get_contents(app_path('Rest/Resources/DriverResource.php'));
        $this->assertStringContainsString("use Lomkit\\Rest\\Fields\\MutationOperation;\n", $code);
        $this->assertStringContainsString(
            "            BelongsTo::make('permit', MutationOperationResource::class)\n"
            ."                ->requiredOnCreation()\n"
            ."                ->withoutOperations(MutationOperation::Detach),\n",
            $code,
        );
        $this->loadGeneratedClass('App\Rest\Resources\DriverResource');
    }

    /** @return array<string, array{string, class-string, array<string, class-string>}> */
    public static function models(): array
    {
        return [
            'every relation to one or many' => ['StoryResource', Story::class, [
                'RelatedDeskResource' => Desk::class,
                'RelatedSummaryResource' => Summary::class,
                'RelatedCommentResource' => Comment::class,
                'Admin/RelatedTopicResource' => Topic::class,
                'RelatedPhotoResource' => Photo::class,
                'RelatedKeywordResource' => Keyword::class,
            ]],
            'the relations through another model' => ['DeskResource', Desk::class, [
                'RelatedStoryResource' => Story::class,
                'RelatedCommentResource' => Comment::class,
                'RelatedSummaryResource' => Summary::class,
                'RelatedKeywordResource' => Keyword::class,
                'RelatedReporterResource' => Reporter::class,
            ]],
            'a relation to the model itself' => ['CommentResource', Comment::class, [
                'RelatedStoryResource' => Story::class,
            ]],
            'the inverse polymorphic many to many relations' => ['KeywordResource', Keyword::class, [
                'RelatedStoryResource' => Story::class,
                'RelatedDeskResource' => Desk::class,
            ]],
            'a many to many relation without pivot columns' => ['TopicResource', Topic::class, [
                'RelatedStoryResource' => Story::class,
            ]],
            'a polymorphic relation to its owner' => ['PhotoResource', Photo::class, []],
        ];
    }
}
