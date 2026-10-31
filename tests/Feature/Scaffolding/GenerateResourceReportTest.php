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

    #[Test]
    public function it_skips_a_relation_method_that_throws_and_generates_the_rest(): void
    {
        $this->writeModel('App\Models\Customer');
        $this->writeModel('App\Models\Invoice', implode("\n\n    ", [
            "public function tenant(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo { throw new \\RuntimeException('No tenant is set.'); }",
            'public function approver(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return null; }',
            'public function archive(): \Illuminate\Database\Eloquent\Relations\HasOne { throw new \LogicException; }',
            'public function customer(): \Illuminate\Database\Eloquent\Relations\BelongsTo { return $this->belongsTo(Customer::class); }',
        ]));
        Schema::create('customers', function (Blueprint $table): void {
            $table->id();
        });
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('customer_id')->constrained();
            $table->foreignId('tenant_id')->nullable();
        });
        $this->artisan('rest:resource', ['name' => 'CustomerResource', '--model' => 'Customer', '--no-interaction' => true])->assertSuccessful();

        $exitCode = Artisan::call('rest:resource', ['name' => 'InvoiceResource', '--model' => 'Invoice', '--generate' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression(
            '/^  InvoiceResource \.+ 3 fields, 1 relation\s*\n'
            .'  Skipped: tenant: tenant\(\) threw RuntimeException: No tenant is set\.\n'
            .'  Skipped: approver: approver\(\) threw TypeError: App\\\\Models\\\\Invoice::approver\(\): Return value must be of type Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\BelongsTo, null returned\n'
            .'  Skipped: archive: archive\(\) threw LogicException\.\n\z/m',
            $output->fetch(),
        );
        $resource = (string) file_get_contents(app_path('Rest/Resources/InvoiceResource.php'));
        $this->assertStringContainsString("            Integer::make('tenant_id')->nullable(),\n", $resource);
        $this->assertStringContainsString("        return [\n            BelongsTo::make('customer', CustomerResource::class)->requiredOnCreation(),\n        ];\n", $resource);
        $this->loadGeneratedClass('App\Rest\Resources\InvoiceResource');
    }

    #[Test]
    public function it_prints_the_message_of_a_relation_method_that_throws_literally_on_one_line(): void
    {
        $this->writeModel('App\Models\Purse', implode("\n\n    ", [
            "public function tenant(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo { throw new \\RuntimeException('Call <info>Tenant::set()</info> first.'); }",
            "public function owner(): \\Illuminate\\Database\\Eloquent\\Relations\\BelongsTo { throw new \\RuntimeException('<info>a</comment> b'); }",
            'public function ledger(): \Illuminate\Database\Eloquent\Relations\HasOne { throw new \RuntimeException("First line.\r\n  Second line.\n"); }',
        ]));
        Schema::create('purses', function (Blueprint $table): void {
            $table->id();
        });

        $exitCode = Artisan::call('rest:resource', ['name' => 'PurseResource', '--model' => 'Purse', '--generate' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression(
            '/^  PurseResource \.+ 1 field, 0 relations\s*\n'
            .'  Skipped: tenant: tenant\(\) threw RuntimeException: Call <info>Tenant::set\(\)<\/info> first\.\n'
            .'  Skipped: owner: owner\(\) threw RuntimeException: <info>a<\/comment> b\n'
            .'  Skipped: ledger: ledger\(\) threw RuntimeException: First line\. Second line\.\n\z/m',
            $output->fetch(),
        );
    }

    private function jsonColumnNote(string $column): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return '';
        }

        return '  Note: '.preg_quote($column, '/').': json column without an array cast on the model, generated as Text\.\n';
    }
}
