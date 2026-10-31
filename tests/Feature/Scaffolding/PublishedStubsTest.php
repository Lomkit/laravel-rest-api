<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Foundation\Events\PublishingStubs;
use Lomkit\Rest\Console\Generation\Stub;
use Lomkit\Rest\Exceptions\InvalidStubException;
use Lomkit\Rest\RestServiceProvider;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class PublishedStubsTest extends ScaffoldingTestCase
{
    private const string RESOURCE_WITH_MODEL = "<?php\n\nnamespace {{ namespace }};\n\nuse {{ namespacedModel }};\n\n// {{ model }}\n";

    private const string POLICY_WITHOUT_SOFT_DELETES = "<?php\n\nnamespace {{ namespace }};\n\nuse {{ namespacedModel }};\nuse {{ namespacedUserModel }};\n\n// {{ user }} {{ model }} {{ modelVariable }}\nclass {{ class }} {}\n";

    private const string PUBLISHED_ACTION = "<?php\n\nnamespace {{ namespace }};\n\nuse Lomkit\Rest\Actions\Action;\n\nclass {{ class }} extends Action {}\n";

    #[Test]
    public function it_offers_every_stub_to_stub_publish(): void
    {
        $this->app['events']->dispatch($event = new PublishingStubs([]));

        $this->assertSame(
            [
                realpath(__DIR__.'/../../../src/Console/stubs/action.stub') => 'rest.action.stub',
                realpath(__DIR__.'/../../../src/Console/stubs/base-controller.stub') => 'rest.base-controller.stub',
                realpath(__DIR__.'/../../../src/Console/stubs/base-resource.stub') => 'rest.base-resource.stub',
                realpath(__DIR__.'/../../../src/Console/stubs/controller.stub') => 'rest.controller.stub',
                realpath(__DIR__.'/../../../src/Console/stubs/instruction.stub') => 'rest.instruction.stub',
                realpath(__DIR__.'/../../../src/Console/stubs/policy.stub') => 'rest.policy.stub',
                realpath(__DIR__.'/../../../src/Console/stubs/resource.stub') => 'rest.resource.stub',
                realpath(__DIR__.'/../../../src/Console/stubs/response.stub') => 'rest.response.stub',
            ],
            $event->stubs,
        );
    }

    #[Test]
    public function it_offers_no_stub_outside_the_console(): void
    {
        (fn () => $this->isRunningInConsole = false)->call($this->app);
        $this->app['events']->forget(PublishingStubs::class);

        $this->app->register(RestServiceProvider::class, force: true);
        $this->app['events']->dispatch($event = new PublishingStubs([]));

        $this->assertSame([], $event->stubs);
    }

    #[Test]
    public function it_generates_from_the_published_stub(): void
    {
        mkdir(base_path('stubs'));
        file_put_contents(base_path('stubs/rest.action.stub'), self::PUBLISHED_ACTION);

        $this->artisan('rest:action', ['name' => 'PublishedAction'])->assertSuccessful();

        $this->assertSame(
            "<?php\n\nnamespace App\Rest\Actions;\n\nuse Lomkit\Rest\Actions\Action;\n\nclass PublishedAction extends Action {}\n",
            file_get_contents(app_path('Rest/Actions/PublishedAction.php')),
        );
        $this->assertSame(base_path('stubs/rest.action.stub'), Stub::Action->path());
    }

    #[Test]
    public function it_never_reads_stubs_outside_the_project(): void
    {
        mkdir(base_path('../stubs'));
        file_put_contents(base_path('../stubs/action.stub'), self::PUBLISHED_ACTION);

        $this->artisan('rest:action', ['name' => 'PackagedAction'])->assertSuccessful();

        $this->assertStringContainsString('public function handle(', file_get_contents(app_path('Rest/Actions/PackagedAction.php')));
        $this->assertSame(Stub::Action->packagePath(), Stub::Action->path());
    }

    /** @param  array<string, string>  $arguments */
    #[Test]
    #[DataProvider('stubsLackingAPlaceholder')]
    public function it_fails_when_a_published_stub_lacks_a_required_placeholder(string $stub, string $contents, string $placeholder, string $command, array $arguments): void
    {
        mkdir(base_path('stubs'));
        file_put_contents(base_path("stubs/rest.{$stub}.stub"), $contents);

        $this->expectExceptionObject(InvalidStubException::missingPlaceholder(base_path("stubs/rest.{$stub}.stub"), $placeholder));

        $this->artisan($command, [...$arguments, '--no-interaction' => true])->run();
    }

    /** @return array<string, array{string, string, string, string, array<string, string>}> */
    public static function stubsLackingAPlaceholder(): array
    {
        return [
            'resource without its model' => ['resource', "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} {}\n", '{{ namespacedModel }}', 'rest:resource', ['name' => 'UnfilledResource', '--model' => 'Post']],
            'resource without the name of its model' => ['resource', "<?php\n\nnamespace {{ namespace }};\n\nuse {{ namespacedModel }};\n\nclass {{ class }} {}\n", '{{ model }}', 'rest:resource', ['name' => 'UnnamedModelResource', '--model' => 'Post']],
            'resource without the imports of its fields' => ['resource', self::RESOURCE_WITH_MODEL."class {{ class }}\n{\n    {{ fields }}\n    {{ relations }}\n}\n", '{{ imports }}', 'rest:resource', ['name' => 'UnimportedResource', '--model' => 'Post']],
            'resource without its fields' => ['resource', self::RESOURCE_WITH_MODEL."{{ imports }}\n\nclass {{ class }}\n{\n    {{ relations }}\n}\n", '{{ fields }}', 'rest:resource', ['name' => 'FieldlessResource', '--model' => 'Post']],
            'resource without its relations' => ['resource', self::RESOURCE_WITH_MODEL."{{ imports }}\n\nclass {{ class }}\n{\n    {{ fields }}\n}\n", '{{ relations }}', 'rest:resource', ['name' => 'RelationlessResource', '--model' => 'Post']],
            'controller without its resource' => ['controller', "<?php\n\nnamespace {{ namespace }};\n\nclass {{ class }} {}\n", '{{ namespacedResource }}', 'rest:controller', ['name' => 'UnfilledController', '--resource' => 'PostResource']],
            'controller without the name of its resource' => ['controller', "<?php\n\nnamespace {{ namespace }};\n\nuse {{ namespacedResource }};\n\nclass {{ class }} {}\n", '{{ resource }}', 'rest:controller', ['name' => 'UnnamedResourceController', '--resource' => 'PostResource']],
            'policy without the restore and force delete methods' => ['policy', self::POLICY_WITHOUT_SOFT_DELETES, '{{ softDeleteMethods }}', 'rest:policy', ['name' => 'UnfilledPolicy', '--model' => User::class]],
            'action without its class' => ['action', "<?php\n\nnamespace {{ namespace }};\n\nclass UnfilledAction {}\n", '{{ class }}', 'rest:action', ['name' => 'UnfilledAction']],
            'response without its namespace' => ['response', "<?php\n\nclass {{ class }} {}\n", '{{ namespace }}', 'rest:response', ['name' => 'UnfilledResponse']],
        ];
    }
}
