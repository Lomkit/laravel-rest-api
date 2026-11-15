<?php

namespace Lomkit\Rest\Tests\Feature\Extensions;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Lomkit\Rest\Exceptions\InvalidQueryExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Concerns\CapturesQueries;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AbstractInstruction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ArchiveInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\BrokenInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CollidingAuthorController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\CollidingAuthorResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MarkedInstruction;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MarkedInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OneMarkedInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TwinInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\TwoMarkedInstructionsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WithArchivedInstruction;
use Lomkit\Rest\Tests\Fixtures\Extensions\FailingExtension;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorController;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\DraftResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\InStatus;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\Fixtures\Publishing\TagResource;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

class ExtensionInstructionsTest extends PackageTestCase
{
    use AssertsExactErrors;
    use CapturesQueries;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config(['rest.extensions' => [ArchiveInstructionsExtension::class]]);
        DB::table('authors')->insert([
            ['id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'api_token' => 'token-1'],
            ['id' => 2, 'first_name' => 'Grace', 'last_name' => 'Hopper', 'api_token' => 'token-2'],
            ['id' => 3, 'first_name' => 'Mary', 'last_name' => 'Shelley', 'api_token' => 'token-3'],
        ]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        BrokenInstructionsExtension::$entry = Instruction::class;

        parent::tearDown();
    }

    #[Test]
    public function it_lists_the_instructions_of_an_extension_in_details(): void
    {
        $response = $this->getJson('/api/authors?with=instructions');

        $response->assertOk()->assertJsonPath('data.instructions', [
            ['name' => 'With Archived', 'uriKey' => 'arc.with-archived', 'text' => false],
            ['name' => 'Marked', 'uriKey' => 'arc.marked', 'text' => false],
        ]);
    }

    #[Test]
    public function it_runs_an_extension_instruction_in_its_own_group_of_a_search(): void
    {
        $this->captureQueries();

        $response = $this->postJson('/api/authors/search', ['search' => [
            'filters' => [['field' => 'id', 'operator' => '!=', 'value' => 2]],
            'instructions' => [['name' => 'arc.with-archived']],
            'selects' => [['field' => 'id']],
        ]]);

        $response->assertOk()->assertJsonPath('data.*.id', [1])->assertJsonPath('meta.instructions', ['arc.with-archived' => ['scope' => 'archived']]);
        $this->assertSame([[
            'select authors.id from authors where (authors.id != ?) and (last_name = ? or first_name = ?) and authors.deleted_at is null order by authors.id desc limit 50 offset 0',
            [2, 'Lovelace', 'Grace'],
        ]], $this->readsOf('authors'));
    }

    #[Test]
    public function it_refuses_the_key_without_the_extension_name(): void
    {
        $response = $this->postJson('/api/authors/search', ['search' => ['instructions' => [['name' => 'with-archived']]]]);

        $this->assertExactErrors($response, ['search.instructions.0.name' => ['The selected search.instructions.0.name is invalid.']]);
    }

    #[Test]
    public function it_refuses_a_key_that_a_resource_instruction_takes(): void
    {
        Rest::resource('colliding-authors', CollidingAuthorController::class)->register();
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidQueryExtensionException::instructionTaken('arc.with-archived', CollidingAuthorResource::class, [null, ['arc', ArchiveInstructionsExtension::class]]));

        $this->getJson('/colliding-authors?with=instructions');
    }

    #[Test]
    public function it_refuses_two_instructions_of_one_extension_with_one_key(): void
    {
        config(['rest.extensions' => [TwinInstructionsExtension::class]]);

        $this->expectExceptionObject(InvalidQueryExtensionException::instructionTaken('arc.with-archived', AuthorResource::class, [['arc', TwinInstructionsExtension::class], ['arc', TwinInstructionsExtension::class]]));

        $this->context()->resource(AuthorResource::class)->definition()->instructions();
    }

    #[Test]
    public function it_names_the_owners_of_a_taken_key(): void
    {
        $this->assertSame(
            'The instruction key [arc.with-archived] of '.AuthorResource::class.' is declared by the [arc] extension '.ArchiveInstructionsExtension::class.' and the resource. Rename one: the key of an extension instruction is the extension name, a dot and the uriKey() of the instruction.',
            InvalidQueryExtensionException::instructionTaken('arc.with-archived', AuthorResource::class, [null, ['arc', ArchiveInstructionsExtension::class]])->getMessage(),
        );
    }

    #[Test]
    public function it_gives_each_resource_its_own_copy_of_an_instance(): void
    {
        $context = $this->context();

        $authors = $context->resource(AuthorResource::class)->definition()->instructions();
        $posts = $context->resource(PostResource::class)->definition()->instructions();
        $marked = array_values(array_filter([...$authors, ...$posts], static fn (Instruction $instruction): bool => $instruction instanceof MarkedInstruction));

        $this->assertCount(2, $marked);
        $this->assertNotSame($marked[0], $marked[1]);
        $this->assertSame([AuthorResource::class, PostResource::class], array_map(static fn (Instruction $instruction): string => $instruction->resource::class, $marked));
        $this->assertSame(['arc.marked', 'arc.marked'], array_map(static fn (Instruction $instruction): string => $instruction->publicUriKey(), $marked));
    }

    #[Test]
    public function it_gives_each_extension_its_own_copy_of_a_shared_instance(): void
    {
        $this->app?->singleton(MarkedInstruction::class);
        config(['rest.extensions' => [OneMarkedInstructionsExtension::class, TwoMarkedInstructionsExtension::class]]);

        $response = $this->getJson('/api/posts?with=instructions');

        $response->assertOk()->assertJsonPath('data.instructions.*.uriKey', ['highlight-posts', 'in-status', 'one.marked', 'two.marked']);
        $this->assertSame('marked', $this->app?->make(MarkedInstruction::class)->publicUriKey());
    }

    /** @param list<class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_lists_the_instructions_in_the_same_order_whatever_the_extension_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);

        $response = $this->getJson('/api/posts?with=instructions');

        $response->assertOk()->assertJsonPath('data.instructions.*.uriKey', ['highlight-posts', 'in-status', 'arc.marked', 'zed.marked']);
    }

    /** @return array<string, array{list<class-string>}> */
    public static function orders(): array
    {
        return [
            'in name order' => [[ArchiveInstructionsExtension::class, MarkedInstructionsExtension::class]],
            'in reverse order' => [[MarkedInstructionsExtension::class, ArchiveInstructionsExtension::class]],
        ];
    }

    #[Test]
    public function it_names_the_registered_class_of_an_extension_bound_to_a_subclass(): void
    {
        config(['rest.extensions' => [TwinInstructionsExtension::class]]);
        $this->app?->bind(TwinInstructionsExtension::class, static fn (): TwinInstructionsExtension => new class extends TwinInstructionsExtension {});

        try {
            $this->context()->resource(AuthorResource::class)->definition()->instructions();
            $this->fail('The taken key was accepted.');
        } catch (InvalidQueryExtensionException $exception) {
            $this->assertSame(InvalidQueryExtensionException::instructionTaken('arc.with-archived', AuthorResource::class, [['arc', TwinInstructionsExtension::class], ['arc', TwinInstructionsExtension::class]])->getMessage(), $exception->getMessage());
            $this->assertTrue($exception->concerns(TwinInstructionsExtension::class));
        }
    }

    #[Test]
    public function it_finds_an_extension_instruction_by_its_public_key(): void
    {
        $definition = $this->context()->resource(AuthorResource::class)->definition();

        $this->assertInstanceOf(WithArchivedInstruction::class, $definition->instruction('arc.with-archived'));
        $this->assertNull($definition->instruction('with-archived'));
    }

    #[Test]
    #[DataProvider('brokenEntries')]
    public function it_names_the_extension_of_an_entry_that_cannot_be_built(string $entry, InvalidQueryExtensionException $exception): void
    {
        config(['rest.extensions' => [BrokenInstructionsExtension::class]]);
        BrokenInstructionsExtension::$entry = $entry;

        $this->expectExceptionObject($exception);

        $this->context()->resource(AuthorResource::class)->definition()->instructions();
    }

    /** @return array<string, array{string, InvalidQueryExtensionException}> */
    public static function brokenEntries(): array
    {
        return [
            'a class that is no instruction' => [AuthorResource::class, InvalidQueryExtensionException::instructionNotAnInstruction('broken', BrokenInstructionsExtension::class, AuthorResource::class, AuthorResource::class)],
            'the base instruction' => [Instruction::class, InvalidQueryExtensionException::instructionBaseListed('broken', BrokenInstructionsExtension::class, AuthorResource::class)],
            'an abstract instruction' => [AbstractInstruction::class, InvalidQueryExtensionException::instructionNotInstantiable('broken', BrokenInstructionsExtension::class, AuthorResource::class, AbstractInstruction::class)],
        ];
    }

    #[Test]
    public function it_names_the_extension_of_an_instruction_bound_to_another_class(): void
    {
        config(['rest.extensions' => [BrokenInstructionsExtension::class]]);
        BrokenInstructionsExtension::$entry = MarkedInstruction::class;
        $this->app?->bind(MarkedInstruction::class, InStatus::class);

        $this->expectExceptionObject(InvalidQueryExtensionException::instructionForeignBinding('broken', BrokenInstructionsExtension::class, AuthorResource::class, MarkedInstruction::class, InStatus::class));

        $this->context()->resource(AuthorResource::class)->definition()->instructions();
    }

    #[Test]
    public function it_reports_the_instruction_problems_of_extensions_in_rest_check(): void
    {
        Rest::resource('colliding-authors', CollidingAuthorController::class)->register();
        BrokenInstructionsExtension::$entry = AbstractInstruction::class;
        config(['rest.extensions' => [ArchiveInstructionsExtension::class, BrokenInstructionsExtension::class]]);

        Artisan::call('rest:check', ['controllers' => [CollidingAuthorController::class, AuthorController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertIsArray($report);
        $this->assertSame(array_map(
            static fn (string $resource): string => $resource.': '.InvalidQueryExtensionException::instructionNotInstantiable('broken', BrokenInstructionsExtension::class, $resource, AbstractInstruction::class)->getMessage(),
            [CollidingAuthorResource::class, AuthorResource::class, PostResource::class, DraftResource::class, TagResource::class],
        ), $report['declarations']['problems']);
    }

    #[Test]
    public function it_reports_a_taken_instruction_key_in_rest_check(): void
    {
        Rest::resource('colliding-authors', CollidingAuthorController::class)->register();

        Artisan::call('rest:check', ['controllers' => [CollidingAuthorController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertIsArray($report);
        $this->assertSame(
            [CollidingAuthorResource::class.': '.InvalidQueryExtensionException::instructionTaken('arc.with-archived', CollidingAuthorResource::class, [null, ['arc', ArchiveInstructionsExtension::class]])->getMessage()],
            $report['declarations']['problems'],
        );
    }

    #[Test]
    public function it_leaves_an_extension_that_cannot_be_built_to_the_extensions_report_of_rest_check(): void
    {
        Rest::resource('colliding-authors', CollidingAuthorController::class)->register();
        config(['rest.extensions' => [FailingExtension::class]]);

        Artisan::call('rest:check', ['controllers' => [CollidingAuthorController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertIsArray($report);
        $this->assertSame([[], 3], [$report['declarations']['problems'], $report['declarations']['instructions']]);
        $this->assertCount(1, $report['extensions']['problems']);
    }

    private function context(): RestContext
    {
        return new RestContext(RestRequest::create('/api/authors/search', 'POST'), $this->app);
    }
}
