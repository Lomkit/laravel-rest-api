<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class QuickStartCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_a_user_resource_and_controller_bound_to_each_other(): void
    {
        $this->artisan('rest:quick-start')->assertSuccessful();

        $this->assertUserResource();
        $this->assertStringContainsString(
            "use App\\Rest\\Resources\\UserResource;\n\nclass UsersController extends Controller\n{\n    public static string \$resource = UserResource::class;\n}",
            file_get_contents(app_path('Rest/Controllers/UsersController.php')),
        );
        $this->assertFileExists(app_path('Rest/Resources/Resource.php'));
        $this->assertFileExists(app_path('Rest/Controllers/Controller.php'));
    }

    #[Test]
    public function it_asks_nothing_when_the_application_has_a_users_table(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });
        $this->writeModel('App\Models\User');

        $this->artisan('rest:quick-start')->assertSuccessful();

        $this->assertUserResource();
    }

    #[Test]
    public function it_registers_the_users_routes_once(): void
    {
        file_put_contents(base_path('routes/api.php'), '<?php');

        $this->artisan('rest:quick-start')->assertSuccessful();
        $this->artisan('rest:quick-start')->assertSuccessful();

        $this->assertSame(
            '<?php'.PHP_EOL."\Lomkit\Rest\Facades\Rest::resource('users', \App\Rest\Controllers\UsersController::class);",
            file_get_contents(base_path('routes/api.php')),
        );
    }

    private function assertUserResource(): void
    {
        $contents = file_get_contents(app_path('Rest/Resources/UserResource.php'));

        $this->assertStringContainsString("use App\\Models\\User;\n", $contents);
        $this->assertStringContainsString("public static string \$model = User::class;\n", $contents);
    }
}
