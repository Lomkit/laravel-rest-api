<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class GeneratedHiddenColumnsTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_leaves_out_a_hidden_column_that_the_fillable_list_of_the_model_omits(): void
    {
        $this->writeModel('App\Models\Member', "protected \$fillable = ['name', 'password'];\n\n    protected \$hidden = ['password', 'remember_token'];");
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->text('password');
            $table->text('remember_token')->nullable();
        });

        $this->artisan('rest:resource', ['name' => 'MemberResource', '--model' => 'Member', '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('3 fields, 0 relations')
            ->expectsOutputToContain('  Note: remember_token: hidden and not fillable on the model, left out.')
            ->assertSuccessful();

        $this->assertStringContainsString(
            "        return [\n"
            ."            ID::make('id'),\n"
            ."            Text::make('name')->creationRules('required'),\n"
            ."            Text::make('password')\n"
            ."                ->writeOnly()\n"
            ."                ->creationRules('required'),\n"
            ."        ];\n",
            (string) file_get_contents(app_path('Rest/Resources/MemberResource.php')),
        );
    }

    #[Test]
    public function it_declares_a_hidden_column_write_only_when_the_model_declares_neither_fillable_nor_guarded(): void
    {
        $this->writeModel('App\Models\Vault', "protected \$hidden = ['pin'];");
        Schema::create('vaults', function (Blueprint $table): void {
            $table->id();
            $table->text('label');
            $table->text('pin')->nullable();
        });

        $this->artisan('rest:resource', ['name' => 'VaultResource', '--model' => 'Vault', '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('3 fields, 0 relations')
            ->doesntExpectOutputToContain('Note:')
            ->assertSuccessful();

        $this->assertStringContainsString(
            "        return [\n"
            ."            ID::make('id'),\n"
            ."            Text::make('label')->creationRules('required'),\n"
            ."            Text::make('pin')\n"
            ."                ->nullable()\n"
            ."                ->writeOnly(),\n"
            ."        ];\n",
            (string) file_get_contents(app_path('Rest/Resources/VaultResource.php')),
        );
    }
}
