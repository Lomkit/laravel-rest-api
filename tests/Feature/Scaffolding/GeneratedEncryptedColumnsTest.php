<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class GeneratedEncryptedColumnsTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_declares_an_encrypted_column_neither_filterable_nor_sortable(): void
    {
        $this->writeModel('App\Models\Patient', "protected \$hidden = ['pin'];\n\n    protected \$casts = ['diagnosis' => 'encrypted', 'pin' => 'encrypted', 'history' => 'encrypted:array'];");
        Schema::create('patients', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->text('diagnosis')->nullable();
            $table->text('pin')->nullable();
            $table->text('history')->nullable();
        });

        $this->artisan('rest:resource', ['name' => 'PatientResource', '--model' => 'Patient', '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('5 fields, 0 relations')
            ->assertSuccessful();

        $this->assertStringContainsString(
            "        return [\n"
            ."            ID::make('id'),\n"
            ."            Text::make('name')->creationRules('required'),\n"
            ."            Text::make('diagnosis')\n"
            ."                ->nullable()\n"
            ."                ->filterable(false)\n"
            ."                ->sortable(false),\n"
            ."            Text::make('pin')\n"
            ."                ->nullable()\n"
            ."                ->writeOnly(),\n"
            ."            Json::make('history')->nullable(),\n"
            ."        ];\n",
            (string) file_get_contents(app_path('Rest/Resources/PatientResource.php')),
        );
    }
}
