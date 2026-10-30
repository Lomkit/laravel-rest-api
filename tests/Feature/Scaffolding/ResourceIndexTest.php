<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Controllers\SuggestedPostsController;
use Lomkit\Rest\Console\Generation\ResourceIndex;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResourceIndexTest extends ScaffoldingTestCase
{
    #[Test]
    public function it_lists_the_concrete_resources_nested_ones_included(): void
    {
        $this->writeResources('Indexed');
        mkdir(app_path('Rest/Resources/Support'));
        file_put_contents(app_path('Rest/Resources/Support/IndexedHelper.php'), "<?php\n\nnamespace App\Rest\Resources\Support;\n\nclass IndexedHelper {}\n");
        file_put_contents(app_path('Rest/Resources/IndexedDraft.php'), '<?php');
        file_put_contents(app_path('Rest/Resources/notes.txt'), 'IndexedNotes');

        $index = new ResourceIndex(app_path('Rest/Resources'), 'App\Rest\Resources\\');

        $this->assertSame(['App\Rest\Resources\Admin\IndexedTagResource', 'App\Rest\Resources\IndexedPostResource'], $index->classes());
        $this->assertSame(['Admin\IndexedTagResource', 'IndexedPostResource'], $index->names());
    }

    #[Test]
    public function it_lists_nothing_when_the_resources_directory_does_not_exist(): void
    {
        $index = new ResourceIndex(app_path('Rest/Resources'), 'App\Rest\Resources\\');

        $this->assertSame([], $index->classes());
    }

    #[Test]
    public function it_suggests_the_concrete_resources_when_it_prompts_for_the_resource(): void
    {
        $this->writeResources('Suggested');

        $this->artisan('rest:controller')
            ->expectsQuestion('What should the controller be named?', 'SuggestedPostsController')
            ->expectsChoice('What resource should this controller rely on? (Optional)', 'Admin\SuggestedTagResource', ['Admin\SuggestedTagResource', 'SuggestedPostResource'], true)
            ->assertSuccessful();

        $this->loadGeneratedClass(SuggestedPostsController::class);

        $this->assertSame('App\Rest\Resources\Admin\SuggestedTagResource', SuggestedPostsController::resourceClass());
    }

    #[Test]
    public function it_does_not_load_the_files_that_do_not_declare_a_concrete_child_class(): void
    {
        $this->writeResources('Guarded');
        $guard = "throw new \\LogicException('This file is loaded.');";
        file_put_contents(app_path('Rest/Resources/Script.php'), "<?php\n\n{$guard}\n");
        file_put_contents(app_path('Rest/Resources/Renamed.php'), "<?php\n\nnamespace App\Rest\Resources;\n\n{$guard}\n\nclass Moved extends Resource {}\n");
        file_put_contents(app_path('Rest/Resources/Draft.php'), "<?php\n\nnamespace App\Rest\Resources;\n\n{$guard}\n\nabstract class Draft extends Resource {}\n");
        file_put_contents(app_path('Rest/Resources/Shape.php'), "<?php\n\nnamespace App\Rest\Resources;\n\n{$guard}\n\ninterface Shape {}\n");
        file_put_contents(app_path('Rest/Resources/Pair.php'), "<?php\n\nnamespace App\Rest\Resources;\n\n{$guard}\n\nclass Pair extends Resource {}\n\nclass Twin extends Resource {}\n");

        $this->artisan('rest:controller')
            ->expectsQuestion('What should the controller be named?', 'GuardedPostsController')
            ->expectsChoice('What resource should this controller rely on? (Optional)', 'GuardedPostResource', ['Admin\GuardedTagResource', 'GuardedPostResource'], true)
            ->assertSuccessful();
    }

    #[Test]
    public function it_maps_each_model_to_its_resource_with_the_planned_resources_first(): void
    {
        $this->writeResources('Mapped');
        $this->artisan('rest:resource', ['name' => 'MappedDraftResource', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:resource', ['name' => 'MappedPostCopyResource', '--model' => 'Post', '--no-interaction' => true])->assertSuccessful();

        $index = (new ResourceIndex(app_path('Rest/Resources'), 'App\Rest\Resources\\'))
            ->withPlanned('App\Rest\Resources\PlannedTagResource', 'App\Models\Tag')
            ->withPlanned('App\Rest\Resources\LaterTagResource', 'App\Models\Tag');

        $this->assertSame(
            ['App\Models\Tag' => 'App\Rest\Resources\PlannedTagResource', 'App\Models\Post' => 'App\Rest\Resources\MappedPostCopyResource'],
            $index->byModel(),
        );
    }

    private function writeResources(string $prefix): void
    {
        $this->artisan('rest:resource', ['name' => "{$prefix}PostResource", '--model' => 'Post', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:resource', ['name' => "Admin/{$prefix}TagResource", '--model' => 'Tag', '--no-interaction' => true])->assertSuccessful();
    }
}
