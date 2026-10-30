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

    private function writeResources(string $prefix): void
    {
        $this->artisan('rest:resource', ['name' => "{$prefix}PostResource", '--model' => 'Post', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:resource', ['name' => "Admin/{$prefix}TagResource", '--model' => 'Tag', '--no-interaction' => true])->assertSuccessful();
    }
}
