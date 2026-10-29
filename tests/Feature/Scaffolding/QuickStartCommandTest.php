<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Controllers\Controller as BaseController;
use App\Rest\Controllers\UsersController;
use App\Rest\Resources\Resource as BaseResource;
use App\Rest\Resources\UserResource;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class QuickStartCommandTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_writes_a_user_resource_and_controller_that_load(): void
    {
        $this->artisan('rest:quick-start')->assertSuccessful();

        $this->loadGeneratedClass(BaseResource::class);
        $this->loadGeneratedClass(UserResource::class);
        $this->loadGeneratedClass(BaseController::class);
        $this->loadGeneratedClass(UsersController::class);

        $this->assertSame('App\Models\User', UserResource::modelClass());
        $this->assertSame(UserResource::class, UsersController::resourceClass());
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
}
