<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Models\HarnessWidget;
use App\Rest\Actions\ShadowedAction;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\AssertionFailedError;
use PHPUnit\Framework\Attributes\Test;
use ReflectionClass;

class ScaffoldingHarnessTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_autoloads_the_application_classes_of_the_skeleton(): void
    {
        file_put_contents(app_path('Models/HarnessWidget.php'), "<?php\n\nnamespace App\Models;\n\nuse Illuminate\Database\Eloquent\Model;\n\nclass HarnessWidget extends Model {}\n");

        $this->assertTrue(class_exists(HarnessWidget::class));
        $this->assertSame(realpath(app_path('Models/HarnessWidget.php')), (new ReflectionClass(HarnessWidget::class))->getFileName());
    }

    #[Test]
    public function it_rejects_a_generated_class_that_another_file_declared(): void
    {
        file_put_contents(base_path('../ShadowedAction.php'), "<?php\n\nnamespace App\Rest\Actions;\n\nclass ShadowedAction {}\n");
        require base_path('../ShadowedAction.php');
        $this->artisan('rest:action', ['name' => 'ShadowedAction'])->assertSuccessful();

        $this->expectException(AssertionFailedError::class);
        $this->expectExceptionMessage(ShadowedAction::class.' was declared by another test');

        $this->loadGeneratedClass(ShadowedAction::class);
    }

    #[Test]
    public function it_builds_the_skeleton_from_an_allowlist_inside_a_private_directory(): void
    {
        $this->assertSame('laravel', basename(base_path()));
        $this->assertStringStartsWith('lomkit-rest-', basename(dirname(base_path())));
        $this->assertFileExists(base_path('bootstrap/app.php'));
        $this->assertDirectoryExists(base_path('routes'));
        $this->assertDirectoryExists(app_path('Models'));
        $this->assertFileDoesNotExist(base_path('.env'));
        $this->assertFileDoesNotExist(storage_path('logs/laravel.log'));
        $this->assertDirectoryDoesNotExist(base_path('public'));
        $this->assertDirectoryDoesNotExist(app_path('Rest'));
    }
}
