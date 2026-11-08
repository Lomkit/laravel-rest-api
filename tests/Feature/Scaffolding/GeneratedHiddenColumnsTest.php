<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

class GeneratedHiddenColumnsTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_leaves_out_a_hidden_column_that_the_fillable_list_of_the_model_omits(): void
    {
        $this->writeModel('App\Models\Member', "protected \$fillable = ['name', 'password'];\n\n    protected \$hidden = ['password', 'pin_hash'];");
        Schema::create('members', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->text('password');
            $table->text('pin_hash')->nullable();
        });

        $this->artisan('rest:resource', ['name' => 'MemberResource', '--model' => 'Member', '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('3 fields, 0 relations')
            ->expectsOutputToContain('  Note: pin_hash: hidden and not fillable on the model, left out.')
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

    #[Test]
    public function it_leaves_out_the_columns_that_the_authentication_flow_writes(): void
    {
        $this->writeModel('App\Models\Account', "protected \$guarded = [];\n\n    protected \$hidden = ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'];");
        Schema::create('accounts', function (Blueprint $table): void {
            $table->id();
            $table->text('name');
            $table->text('password');
            $table->string('remember_token', 100)->nullable();
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
        });

        $exitCode = Artisan::call('rest:resource', ['name' => 'AccountResource', '--model' => 'Account', '--generate' => true, '--no-interaction' => true], $output = new BufferedOutput);

        $this->assertSame(0, $exitCode);
        $this->assertMatchesRegularExpression(
            '/^  AccountResource \.+ 3 fields, 0 relations\s*\n'
            .'  Note: remember_token: written by the authentication flow, left out\.\n'
            .'  Note: two_factor_secret: written by the authentication flow, left out\.\n'
            .'  Note: two_factor_recovery_codes: written by the authentication flow, left out\.\n'
            .'  Note: two_factor_confirmed_at: written by the authentication flow, left out\.\n$/m',
            $output->fetch(),
        );
        $this->assertStringContainsString(
            "        return [\n"
            ."            ID::make('id'),\n"
            ."            Text::make('name')->creationRules('required'),\n"
            ."            Text::make('password')\n"
            ."                ->writeOnly()\n"
            ."                ->creationRules('required'),\n"
            ."        ];\n",
            (string) file_get_contents(app_path('Rest/Resources/AccountResource.php')),
        );
    }

    #[Test]
    public function it_leaves_out_the_remember_token_column_that_an_authenticatable_model_names(): void
    {
        (new Filesystem)->ensureDirectoryExists(app_path('Models'));
        file_put_contents(app_path('Models/Operator.php'), "<?php\n\nnamespace App\Models;\n\nuse Illuminate\Foundation\Auth\User;\n\nclass Operator extends User\n{\n    protected \$rememberTokenName = 'login_token';\n\n    protected \$hidden = ['login_token'];\n}\n");
        Schema::create('operators', function (Blueprint $table): void {
            $table->id();
            $table->text('email');
            $table->text('login_token')->nullable();
        });

        $this->artisan('rest:resource', ['name' => 'OperatorResource', '--model' => 'Operator', '--generate' => true, '--no-interaction' => true])
            ->expectsOutputToContain('2 fields, 0 relations')
            ->expectsOutputToContain('  Note: login_token: written by the authentication flow, left out.')
            ->assertSuccessful();

        $this->assertStringContainsString(
            "        return [\n"
            ."            ID::make('id'),\n"
            ."            Text::make('email')->creationRules('required'),\n"
            ."        ];\n",
            (string) file_get_contents(app_path('Rest/Resources/OperatorResource.php')),
        );
    }
}
