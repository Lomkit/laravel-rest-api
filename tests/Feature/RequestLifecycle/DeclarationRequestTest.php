<?php

namespace Lomkit\Rest\Tests\Feature\RequestLifecycle;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\RequestLifecycle\Stubs\StaffNotedPostController;
use Lomkit\Rest\Tests\Fixtures\Accounts\Accounts;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class DeclarationRequestTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Accounts, new Publishing];
    }

    #[Test]
    public function it_gives_a_resource_the_request_of_its_context(): void
    {
        $request = RestRequest::create('/api/posts/search', 'POST');
        $resource = (new RestContext($request, $this->app))->resource(PostResource::class);

        $this->assertSame($request, $resource->request());
    }

    #[Test]
    public function it_gives_an_action_and_an_instruction_the_request_of_their_resource(): void
    {
        $request = RestRequest::create('/api/posts/search', 'POST');
        $resource = (new RestContext($request, $this->app))->resource(PostResource::class);

        $this->assertSame($request, $resource->action('publish-posts')->request());
        $this->assertSame($request, $resource->instruction('highlight-posts')?->request());
    }

    #[Test]
    public function it_reads_the_user_of_the_request_in_the_fields_of_an_action(): void
    {
        Rest::resource('staff-noted-posts', StaffNotedPostController::class)->register();
        $payload = ['fields' => [['name' => 'note', 'value' => 'Checked']]];

        $staffResponse = $this->actingAs(User::factory()->staff()->create())
            ->postJson('/staff-noted-posts/actions/staff-noted-publish-posts', $payload);
        $customerResponse = $this->actingAs(User::factory()->create())
            ->postJson('/staff-noted-posts/actions/staff-noted-publish-posts', $payload);

        $staffResponse->assertOk()->assertExactJson(['data' => ['impacted' => 0]]);
        $customerResponse->assertUnprocessable()->assertExactJson([
            'message' => 'The selected fields.0.name is invalid.',
            'errors' => ['fields.0.name' => ['The selected fields.0.name is invalid.']],
        ]);
    }
}
