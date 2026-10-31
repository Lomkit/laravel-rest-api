<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Gate;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Library\OpenLibraryPolicy;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('auth.providers.users.model', 'App\Models\User')]
class MakePolicyCommandTest extends ScaffoldingTestCase
{
    private const string LEDGER_POLICY = <<<'PHP'
        <?php

        namespace App\Policies;

        use App\Models\Ledger;
        use App\Models\User;

        class LedgerPolicy
        {
            public function viewAny(User $user): bool
            {
                return true;
            }

            public function view(User $user, Ledger $ledger): bool
            {
                return true;
            }

            public function create(User $user): bool
            {
                return false;
            }

            public function update(User $user, Ledger $ledger): bool
            {
                return false;
            }

            public function delete(User $user, Ledger $ledger): bool
            {
                return false;
            }
        }

        PHP;

    #[Test]
    public function it_writes_a_policy_that_lets_signed_in_users_read_and_refuses_every_write(): void
    {
        $this->writeModel('App\Models\Ledger');

        $this->artisan('rest:policy', ['name' => 'LedgerPolicy', '--model' => 'Ledger', '--no-interaction' => true])
            ->expectsOutputToContain('Policy [app/Policies/LedgerPolicy.php] created successfully.')
            ->assertSuccessful();

        $this->assertSame(self::LEDGER_POLICY, $this->policy('LedgerPolicy'));
    }

    #[Test]
    public function it_refuses_restore_and_force_delete_for_a_soft_deletable_model(): void
    {
        $this->writeModel('App\Models\Memo', 'use \Illuminate\Database\Eloquent\SoftDeletes;');

        $this->artisan('rest:policy', ['name' => 'MemoPolicy', '--model' => 'Memo', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringEndsWith(
            "    public function delete(User \$user, Memo \$memo): bool\n    {\n        return false;\n    }\n\n"
            ."    public function restore(User \$user, Memo \$memo): bool\n    {\n        return false;\n    }\n\n"
            ."    public function forceDelete(User \$user, Memo \$memo): bool\n    {\n        return false;\n    }\n}\n",
            $this->policy('MemoPolicy'),
        );
    }

    #[Test]
    public function it_types_the_user_with_the_model_of_the_default_guard(): void
    {
        config([
            'auth.defaults.guard' => 'staff',
            'auth.guards.staff' => ['driver' => 'session', 'provider' => 'staff'],
            'auth.providers.staff' => ['driver' => 'eloquent', 'model' => User::class],
        ]);
        $this->writeModel('App\Models\Shift');

        $this->artisan('rest:policy', ['name' => 'ShiftPolicy', '--model' => 'Shift', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("use App\Models\Shift;\nuse Lomkit\Rest\Tests\Fixtures\Accounts\User;\n", $this->policy('ShiftPolicy'));
        $this->assertStringContainsString('public function viewAny(User $user): bool', $this->policy('ShiftPolicy'));
    }

    #[Test]
    public function it_writes_a_policy_for_the_user_model_itself(): void
    {
        config(['auth.providers.users.model' => 'App\Models\Staff\User']);
        $this->writeModel('App\Models\Staff\User');

        $this->artisan('rest:policy', ['name' => 'Staff/UserPolicy', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("namespace App\Policies\Staff;\n\nuse App\Models\Staff\User;\n\nclass UserPolicy\n", $this->policy('Staff/UserPolicy'));
        $this->assertStringContainsString('public function view(User $user, User $model): bool', $this->policy('Staff/UserPolicy'));
    }

    #[Test]
    public function it_aliases_a_model_named_like_the_user_model(): void
    {
        $this->writeModel('App\Models\Crew\User');

        $this->artisan('rest:policy', ['name' => 'CrewUserPolicy', '--model' => 'Crew/User', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("use App\Models\Crew\User;\nuse App\Models\User as UserUser;\n", $this->policy('CrewUserPolicy'));
        $this->assertStringContainsString('public function update(UserUser $user, User $model): bool', $this->policy('CrewUserPolicy'));
    }

    #[Test]
    public function it_keeps_an_existing_policy_unless_forced(): void
    {
        $this->writeModel('App\Models\Ticket');
        $this->artisan('rest:policy', ['name' => 'TicketPolicy', '--model' => 'Ticket', '--no-interaction' => true])->assertSuccessful();
        file_put_contents(app_path('Policies/TicketPolicy.php'), '<?php // edited');

        $this->artisan('rest:policy', ['name' => 'TicketPolicy', '--model' => 'Ticket', '--no-interaction' => true])
            ->expectsOutputToContain('Policy already exists.')
            ->assertSuccessful();
        $this->assertSame('<?php // edited', $this->policy('TicketPolicy'));

        $this->artisan('rest:policy', ['name' => 'TicketPolicy', '--model' => 'Ticket', '-f' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertStringContainsString('class TicketPolicy', $this->policy('TicketPolicy'));
    }

    #[Test]
    public function it_reports_once_that_the_policy_of_the_same_name_already_exists(): void
    {
        $this->writeModel('App\Models\Medal');
        $this->artisan('rest:policy', ['name' => 'MedalPolicy', '--model' => 'Medal', '--no-interaction' => true])->assertSuccessful();

        $this->artisan('rest:policy', ['name' => 'MedalPolicy', '--model' => 'Medal', '--no-interaction' => true])
            ->expectsOutputToContain('Policy already exists.')
            ->doesntExpectOutputToContain('already has the policy')
            ->assertSuccessful();
    }

    #[Test]
    public function it_keeps_the_policy_already_registered_for_the_model(): void
    {
        $this->writeModel('App\Models\Voucher');
        Gate::policy('App\Models\Voucher', OpenLibraryPolicy::class);

        $this->artisan('rest:policy', ['name' => 'VoucherPolicy', '--model' => 'Voucher', '--no-interaction' => true])
            ->expectsOutputToContain('The model [App\Models\Voucher] already has the policy ['.OpenLibraryPolicy::class.'].')
            ->doesntExpectOutputToContain('Policy already exists.')
            ->assertSuccessful();

        $this->assertFileDoesNotExist(app_path('Policies/VoucherPolicy.php'));
    }

    #[Test]
    public function it_warns_that_gate_keeps_the_registered_policy_after_a_forced_write(): void
    {
        $this->writeModel('App\Models\Badge');
        Gate::policy('App\Models\Badge', OpenLibraryPolicy::class);

        $this->artisan('rest:policy', ['name' => 'BadgePolicy', '--model' => 'Badge', '--force' => true, '--no-interaction' => true])
            ->doesntExpectOutputToContain('already has the policy')
            ->expectsOutputToContain('Policy [app/Policies/BadgePolicy.php] created successfully.')
            ->expectsOutputToContain('Gate still uses the policy ['.OpenLibraryPolicy::class.'] for the model [App\Models\Badge]. Register [App\Policies\BadgePolicy] with Gate::policy() to use it.')
            ->assertSuccessful();

        $this->assertFileExists(app_path('Policies/BadgePolicy.php'));
    }

    #[Test]
    public function it_does_not_warn_when_gate_finds_the_policy_that_it_replaces(): void
    {
        $this->writeModel('App\Models\Stamp');
        $this->artisan('rest:policy', ['name' => 'StampPolicy', '--model' => 'Stamp', '--no-interaction' => true])->assertSuccessful();

        $this->artisan('rest:policy', ['name' => 'StampPolicy', '--model' => 'Stamp', '--force' => true, '--no-interaction' => true])
            ->doesntExpectOutputToContain('Gate still uses')
            ->assertSuccessful();
    }

    private function policy(string $name): string
    {
        return (string) file_get_contents(app_path("Policies/{$name}.php"));
    }
}
