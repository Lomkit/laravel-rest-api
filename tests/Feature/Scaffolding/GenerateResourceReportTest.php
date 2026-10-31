<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Tests\Fixtures\Newsroom\Article;
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
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

class GenerateResourceReportTest extends ScaffoldingTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Newsroom];
    }

    #[Test]
    public function it_reports_the_generated_fields_and_the_notes(): void
    {
        $exitCode = Artisan::call('rest:resource', ['name' => 'ReportedArticleResource', '--model' => Article::class, '--generate' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression(
            '/^  ReportedArticleResource \.+ 26 fields, 0 relations\s*\n'
            .$this->jsonColumnNote('metadata')
            .'  Note: thumbnail: no field type matches the column type \w+, generated as Text\.\n'
            .'  Note: internal_token: hidden and not fillable on the model, left out\.\n/m',
            $output->fetch(),
        );
    }

    #[Test]
    public function it_reports_a_single_field_in_the_singular(): void
    {
        $this->writeModel('App\Models\Counter');
        Schema::create('counters', function (Blueprint $table): void {
            $table->id();
        });

        $this->artisan('rest:resource', ['name' => 'CounterResource', '--model' => 'Counter', '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('1 field, 0 relations')
            ->doesntExpectOutputToContain('Note:')
            ->assertSuccessful();
    }

    #[Test]
    public function it_reports_the_skipped_relations_and_the_notes_of_the_pivot_fields(): void
    {
        foreach (['Desk' => Desk::class, 'Summary' => Summary::class, 'Comment' => Comment::class, 'Topic' => Topic::class, 'Photo' => Photo::class, 'Keyword' => Keyword::class] as $name => $model) {
            $this->artisan('rest:resource', ['name' => "Reported{$name}Resource", '--model' => $model, '--no-interaction' => true])->assertSuccessful();
        }

        $exitCode = Artisan::call('rest:resource', ['name' => 'ReportedStoryResource', '--model' => Story::class, '--generate' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression(
            '/^  ReportedStoryResource \.+ 6 fields, 7 relations\s*\n'
            .'  Skipped: reporter: no resource for '.preg_quote(Reporter::class, '/').'\. Generate one first with php artisan rest:resource ReporterResource --model=Lomkit\/Rest\/Tests\/Fixtures\/Newsroom\/Reporter --generate, then run this command again with --force\.\n'
            .'  Note: topics\.pivot\.badge: no field type matches the column type \w+, generated as Text\.\n'
            .$this->jsonColumnNote('topics.pivot.details').'$/m',
            $output->fetch(),
        );
    }

    #[Test]
    public function it_reports_each_declared_pivot_column_that_the_pivot_table_lacks(): void
    {
        $this->writeModel('App\Models\Athlete');
        $this->writeModel('App\Models\Squad', implode("\n\n    ", [
            'public function athletes(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(Athlete::class)->withPivot("position", "rank", "created_at")->withTimestamps(); }',
            'public function coaches(): \Illuminate\Database\Eloquent\Relations\BelongsToMany { return $this->belongsToMany(Athlete::class, "squad_coaches")->withPivot("since"); }',
        ]));
        Schema::create('athletes', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('squads', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('athlete_squad', function (Blueprint $table): void {
            $table->foreignId('athlete_id')->constrained();
            $table->foreignId('squad_id')->constrained();
            $table->unsignedInteger('position')->nullable();
        });
        Schema::create('squad_coaches', function (Blueprint $table): void {
            $table->foreignId('athlete_id')->constrained();
            $table->foreignId('squad_id')->constrained();
        });
        $this->artisan('rest:resource', ['name' => 'AthleteResource', '--model' => 'Athlete', '--no-interaction' => true])->assertSuccessful();

        $exitCode = Artisan::call('rest:resource', ['name' => 'SquadResource', '--model' => 'Squad', '--generate' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression(
            '/^  SquadResource \.+ 1 field, 2 relations\s*\n'
            .'  Note: athletes\.pivot\.rank: no such column in the athlete_squad pivot table, left out\.\n'
            .'  Note: athletes\.pivot\.created_at: no such column in the athlete_squad pivot table, left out\.\n'
            .'  Note: athletes\.pivot\.updated_at: no such column in the athlete_squad pivot table, left out\.\n'
            .'  Note: coaches\.pivot\.since: no such column in the squad_coaches pivot table, left out\.\n$/m',
            $output->fetch(),
        );
        $this->assertStringContainsString(
            "            BelongsToMany::make('athletes', AthleteResource::class)->pivotFields([\n"
            ."                Integer::make('position')->nullable(),\n"
            ."            ]),\n"
            ."            BelongsToMany::make('coaches', AthleteResource::class),\n",
            (string) file_get_contents(app_path('Rest/Resources/SquadResource.php')),
        );
    }

    #[Test]
    public function it_reports_a_morph_to_relation_as_skipped(): void
    {
        $this->artisan('rest:resource', ['name' => 'SkippedPhotoResource', '--model' => Photo::class, '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('4 fields, 0 relations')
            ->expectsOutputToContain('  Skipped: imageable: MorphTo relations are not generated, declare it in relations() by hand.')
            ->assertSuccessful();
    }

    #[Test]
    public function it_keeps_the_key_of_a_skipped_relation_writable_and_names_an_app_model_by_its_short_name(): void
    {
        $this->writeModel('App\Models\Writer');
        $this->writeModel('App\Models\Byline', 'public function writer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Writer::class); }');
        Schema::create('writers', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('bylines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('writer_id')->constrained();
        });

        $this->artisan('rest:resource', ['name' => 'BylineResource', '--model' => 'Byline', '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('  Skipped: writer: no resource for App\Models\Writer. Generate one first with php artisan rest:resource WriterResource --model=Writer --generate, then run this command again with --force.')
            ->assertSuccessful();

        $resource = (string) file_get_contents(app_path('Rest/Resources/BylineResource.php'));
        $this->assertStringContainsString("            Integer::make('writer_id')->creationRules('required'),\n", $resource);
        $this->assertStringContainsString("    public function relations(RestRequest \$request): array\n    {\n        return [];\n    }", $resource);
    }

    private function jsonColumnNote(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return '';
        }

        return '  Note: '.preg_quote($column, '/').': json column without an array cast on the model, generated as Text\.\n';
    }
}
