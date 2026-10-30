<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('auth.providers.users.model', 'App\Models\User')]
class PolicyModelGuessTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_guesses_the_model_from_the_name_of_the_policy(): void
    {
        $this->writeModel('App\Models\Admin\Invoice');

        $this->artisan('rest:policy', ['name' => 'Admin/InvoicePolicy', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("namespace App\Policies\Admin;\n\nuse App\Models\Admin\Invoice;\nuse App\Models\User;\n", $this->policy('Admin/InvoicePolicy'));
        $this->assertStringContainsString('public function update(User $user, Invoice $invoice): bool', $this->policy('Admin/InvoicePolicy'));
    }

    #[Test]
    public function it_guesses_a_model_whose_name_ends_with_resource(): void
    {
        $this->writeModel('App\Models\LearningResource');
        $this->writeModel('App\Models\Learning');

        $this->artisan('rest:policy', ['name' => 'LearningResourcePolicy', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("use App\Models\LearningResource;\n", $this->policy('LearningResourcePolicy'));
        $this->assertStringContainsString('public function view(User $user, LearningResource $learningResource): bool', $this->policy('LearningResourcePolicy'));
    }

    #[Test]
    public function it_guesses_a_model_of_the_root_namespace_when_the_application_has_no_models_directory(): void
    {
        rmdir(app_path('Models'));
        $this->writeModel('App\Folio');

        $this->artisan('rest:policy', ['name' => 'FolioPolicy', '--no-interaction' => true])->assertSuccessful();

        $this->assertStringContainsString("use App\Folio;\nuse App\Models\User;\n", $this->policy('FolioPolicy'));
    }

    #[Test]
    public function it_asks_for_the_model(): void
    {
        $this->writeModel('App\Models\Receipt');

        $this->artisan('rest:policy', ['name' => 'AskedReceiptPolicy'])
            ->expectsQuestion('What model should this policy authorize?', 'Receipt')
            ->assertSuccessful();

        $this->assertStringContainsString('public function view(User $user, Receipt $receipt): bool', $this->policy('AskedReceiptPolicy'));
    }

    #[Test]
    public function it_does_not_ask_for_a_model_that_is_given(): void
    {
        $this->writeModel('App\Models\Coupon');

        $this->artisan('rest:policy', ['name' => 'GivenCouponPolicy', '--model' => 'Coupon'])->assertSuccessful();

        $this->assertStringContainsString('public function view(User $user, Coupon $coupon): bool', $this->policy('GivenCouponPolicy'));
    }

    #[Test]
    public function it_requires_a_model(): void
    {
        $this->artisan('rest:policy', ['name' => 'StrayPolicy', '--no-interaction' => true])
            ->expectsOutputToContain('No model was given or found for this policy. Pass --model to set it.')
            ->assertFailed();

        $this->assertFileDoesNotExist(app_path('Policies/StrayPolicy.php'));
    }

    #[Test]
    public function it_requires_an_existing_model(): void
    {
        $this->artisan('rest:policy', ['name' => 'GhostPolicy', '--model' => 'Ghost', '--no-interaction' => true])
            ->expectsOutputToContain('The model [App\Models\Ghost] does not exist.')
            ->assertFailed();

        $this->assertFileDoesNotExist(app_path('Policies/GhostPolicy.php'));
    }

    private function policy(string $name): string
    {
        return (string) file_get_contents(app_path("Policies/{$name}.php"));
    }
}
