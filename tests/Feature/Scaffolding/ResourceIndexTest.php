<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use App\Rest\Controllers\SuggestedPostsController;
use Lomkit\Rest\Console\Generation\ResourceIndex;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\RequiresPhp;
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

        $index = ResourceIndex::scan(app_path('Rest/Resources'), 'App\Rest\Resources\\', base_path());

        $this->assertSame(['App\Rest\Resources\Admin\IndexedTagResource', 'App\Rest\Resources\IndexedPostResource'], $index->classes());
        $this->assertSame(['Admin\IndexedTagResource', 'IndexedPostResource'], $index->names());
    }

    #[Test]
    public function it_lists_nothing_when_the_resources_directory_does_not_exist(): void
    {
        $index = ResourceIndex::scan(app_path('Rest/Resources'), 'App\Rest\Resources\\', base_path());

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
    public function it_leaves_out_with_a_note_the_resources_that_fail_to_load(): void
    {
        $this->writeResources('Failing');
        file_put_contents(app_path('Rest/Resources/FailingOrphanResource.php'), "<?php\n\nnamespace App\Rest\Resources;\n\nclass FailingOrphanResource extends FailingParentResource {}\n");
        file_put_contents(app_path('Rest/Resources/Admin/FailingSealedResource.php'), "<?php\n\nnamespace App\Rest\Resources\Admin;\n\nuse App\Rest\Resources\Resource;\n\nclass FailingSealedResource extends Resource implements \App\Contracts\FailingSealed {}\n");
        file_put_contents(app_path('Rest/Resources/FailingDraftResource.php'), "<?php\n\nnamespace App\Rest\Resources;\n\nclass FailingDraftResource extends Resource\n{\n    public function\n}\n");

        $index = ResourceIndex::scan(app_path('Rest/Resources'), 'App\Rest\Resources\\', base_path());

        $this->assertSame(['App\Rest\Resources\Admin\FailingTagResource', 'App\Rest\Resources\FailingPostResource'], $index->classes());
        $this->assertSame([
            'App\Rest\Resources\Admin\FailingSealedResource is skipped: Interface "App\Contracts\FailingSealed" not found (app/Rest/Resources/Admin/FailingSealedResource.php:7).',
            'App\Rest\Resources\FailingDraftResource is skipped: syntax error, unexpected token "}" (app/Rest/Resources/FailingDraftResource.php:8).',
            'App\Rest\Resources\FailingOrphanResource is skipped: Class "App\Rest\Resources\FailingParentResource" not found (app/Rest/Resources/FailingOrphanResource.php:5).',
        ], $index->skipped());
    }

    #[Test]
    public function it_notes_the_file_relative_to_a_base_path_reached_through_a_symlink(): void
    {
        $this->writeResources('Linked');
        file_put_contents(app_path('Rest/Resources/LinkedOrphanResource.php'), "<?php\n\nnamespace App\Rest\Resources;\n\nclass LinkedOrphanResource extends LinkedParentResource {}\n");
        $link = dirname(base_path()).'/linked-laravel';
        symlink(base_path(), $link);

        $index = ResourceIndex::scan($link.'/app/Rest/Resources', 'App\Rest\Resources\\', $link);

        $this->assertSame(['App\Rest\Resources\Admin\LinkedTagResource', 'App\Rest\Resources\LinkedPostResource'], $index->classes());
        $this->assertSame(['App\Rest\Resources\LinkedOrphanResource is skipped: Class "App\Rest\Resources\LinkedParentResource" not found (app/Rest/Resources/LinkedOrphanResource.php:5).'], $index->skipped());
    }

    #[Test]
    #[RequiresPhp('>= 8.5.0')]
    public function it_leaves_out_with_a_note_a_resource_whose_trait_is_missing(): void
    {
        $this->writeResources('Traited');
        file_put_contents(app_path('Rest/Resources/TraitedAuditResource.php'), "<?php\n\nnamespace App\Rest\Resources;\n\nclass TraitedAuditResource extends Resource\n{\n    use \App\Concerns\TraitedAudits;\n}\n");

        $index = ResourceIndex::scan(app_path('Rest/Resources'), 'App\Rest\Resources\\', base_path());

        $this->assertSame(['App\Rest\Resources\Admin\TraitedTagResource', 'App\Rest\Resources\TraitedPostResource'], $index->classes());
        $this->assertSame(['App\Rest\Resources\TraitedAuditResource is skipped: Trait "App\Concerns\TraitedAudits" not found (app/Rest/Resources/TraitedAuditResource.php:5).'], $index->skipped());
    }

    #[Test]
    public function it_notes_the_resources_that_fail_to_load_when_it_prompts_for_the_resource(): void
    {
        $this->writeResources('Noted');
        mkdir(app_path('Support'));
        file_put_contents(app_path('Support/NotedBaseResource.php'), "<?php\n\nnamespace App\Support;\n\nthrow new \LogicException('Finish <comment>NotedBaseResource</comment> first');\n");
        file_put_contents(app_path('Rest/Resources/NotedDraftResource.php'), "<?php\n\nnamespace App\Rest\Resources;\n\nclass NotedDraftResource extends \App\Support\NotedBaseResource {}\n");
        file_put_contents(app_path('Rest/Resources/NotedScriptResource.php'), "<?php\n\nnamespace App\Rest\Resources;\n\nthrow new \LogicException('Not ready');\n\nclass NotedScriptResource extends \Lomkit\Rest\Http\Resource {}\n");
        $this->assertTrue(class_exists(Resource::class));

        $this->artisan('rest:controller')
            ->expectsQuestion('What should the controller be named?', 'NotedTagsController')
            ->expectsOutput('  Note: App\Rest\Resources\NotedDraftResource is skipped: Finish <comment>NotedBaseResource</comment> first (app/Support/NotedBaseResource.php:5).')
            ->expectsOutput('  Note: App\Rest\Resources\NotedScriptResource is skipped: Not ready (app/Rest/Resources/NotedScriptResource.php:5).')
            ->expectsChoice('What resource should this controller rely on? (Optional)', 'Admin\NotedTagResource', ['Admin\NotedTagResource', 'NotedPostResource'], true)
            ->assertSuccessful();
    }

    #[Test]
    public function it_maps_each_model_to_its_resource_with_the_planned_resources_first(): void
    {
        $this->writeResources('Mapped');
        $this->artisan('rest:resource', ['name' => 'MappedDraftResource', '--no-interaction' => true])->assertSuccessful();
        $this->artisan('rest:resource', ['name' => 'MappedPostCopyResource', '--model' => 'Post', '--no-interaction' => true])->assertSuccessful();

        $index = ResourceIndex::scan(app_path('Rest/Resources'), 'App\Rest\Resources\\', base_path())
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
