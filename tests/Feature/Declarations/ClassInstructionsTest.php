<?php

namespace Lomkit\Rest\Tests\Feature\Declarations;

use Illuminate\Support\Facades\Artisan;
use LogicException;
use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\AbstractDeskInstruction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeclarationLog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTagMatch;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTagResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketBacklog;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketController;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTicketResource;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\DeskTitleMatch;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\EngineOnlyDeskInstruction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\LabelledDeskInstruction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\NamedDeskInstruction;
use Lomkit\Rest\Tests\Feature\Declarations\Stubs\TicketJournal;
use Lomkit\Rest\Tests\Feature\Metrics\Stubs\NewestFirst;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

class ClassInstructionsTest extends DeclarationTestCase
{
    #[Test]
    public function it_searches_with_a_class_instruction_that_the_container_builds_once(): void
    {
        $this->seedTickets();
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->postJson('/desk-tickets/search', ['search' => ['instructions' => [['name' => 'title-match']]]])
            ->assertOk()
            ->assertJsonPath('data.*.title', ['Printer jam']);

        $this->assertSame([DeskTitleMatch::class], DeclarationLog::$built);
        $this->assertSame(['matched jam'], DeclarationLog::$journal);
    }

    #[Test]
    public function it_runs_an_instance_and_a_class_instruction_of_one_list_in_its_order(): void
    {
        $this->seedTickets();
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->postJson('/desk-tickets/search', ['search' => ['instructions' => [
            ['name' => 'title-match', 'fields' => [['name' => 'word', 'value' => 'toner']]],
            ['name' => 'newest-first'],
        ]]])
            ->assertOk()
            ->assertJsonPath('data.*.title', ['Printer toner']);

        $this->assertSame(['matched toner'], DeclarationLog::$journal);
        $instructions = $this->desk()->definition()->instructions();

        $this->assertSame([0, 1], array_keys($instructions));
        $this->assertSame([NewestFirst::class, DeskTitleMatch::class], array_map(get_class(...), $instructions));
    }

    #[Test]
    public function it_details_a_class_instruction_as_the_matching_instance(): void
    {
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $byClass = $this->getJson('/desk-tickets?with=instructions.fields,instructions.meta')
            ->assertOk()
            ->assertJsonPath('data.instructions.*.uriKey', ['newest-first', 'title-match'])
            ->assertJsonPath('data.instructions.1.fields.0.name', 'word')
            ->json();
        DeskTicketResource::$instructionsByClass = false;
        $byInstance = $this->getJson('/desk-tickets?with=instructions.fields,instructions.meta')->assertOk()->json();

        $this->assertSame($byInstance, $byClass);
    }

    #[Test]
    public function it_builds_a_class_instruction_only_when_a_request_reads_the_instructions(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->postJson('/desk-tickets/mutate', ['mutate' => [['operation' => 'update', 'key' => $jam->id, 'attributes' => ['title' => 'Printer stuck']]]])->assertOk();
        $this->postJson('/desk-tickets/metrics/ticket-backlog')->assertOk();

        $this->assertSame([DeskTicketBacklog::class], DeclarationLog::$built);

        $this->postJson('/desk-tickets/search', ['search' => ['instructions' => [['name' => 'title-match']]]])->assertOk();

        $this->assertSame([DeskTicketBacklog::class, DeskTitleMatch::class], DeclarationLog::$built);
    }

    #[Test]
    public function it_builds_a_class_instruction_once_however_often_the_instructions_are_read(): void
    {
        $resource = $this->desk();
        $definition = $resource->definition();

        $first = $definition->instructions();
        $second = $definition->instructions();
        $found = $definition->instruction('title-match');

        $this->assertSame([DeskTitleMatch::class], DeclarationLog::$built);
        $this->assertSame($first, $second);
        $this->assertSame($first[1], $found);
        $this->assertSame($resource, $first[1]->resource);
    }

    #[Test]
    public function it_lets_a_class_instruction_take_its_name_from_a_method(): void
    {
        DeskTicketResource::$moreInstructions = [NamedDeskInstruction::class];

        $this->assertSame('Named On Build', $this->desk()->definition()->instruction('named-on-build')?->name());
    }

    #[Test]
    #[DataProvider('invalidEntries')]
    public function it_refuses_an_invalid_class_entry_when_the_instructions_are_read(string $entry, LogicException $expected): void
    {
        DeskTicketResource::$moreInstructions = [$entry];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->expectExceptionObject($expected);

        $this->desk()->definition()->instructions();
    }

    /** @return array<string, array{string, LogicException}> */
    public static function invalidEntries(): array
    {
        return [
            'a string that is not a class' => ['Desk\Missing\Instruction', InvalidInstructionException::notAnInstruction(DeskTicketResource::class, 'Desk\Missing\Instruction')],
            'a class that is not an instruction' => [TicketJournal::class, InvalidInstructionException::notAnInstruction(DeskTicketResource::class, TicketJournal::class)],
            'the instruction class itself' => [Instruction::class, InvalidInstructionException::baseListed(DeskTicketResource::class)],
            'an abstract instruction' => [AbstractDeskInstruction::class, InvalidInstructionException::notInstantiable(DeskTicketResource::class, AbstractDeskInstruction::class)],
        ];
    }

    #[Test]
    public function it_names_the_resource_and_the_entry_in_each_error(): void
    {
        $this->assertSame(
            DeskTicketResource::class.'::instructions() lists [Desk\Missing\Instruction], which is not a class that extends '.Instruction::class.'.',
            InvalidInstructionException::notAnInstruction(DeskTicketResource::class, 'Desk\Missing\Instruction')->getMessage(),
        );
        $this->assertSame(
            DeskTicketResource::class.'::instructions() lists '.Instruction::class.' itself. List a class that extends it.',
            InvalidInstructionException::baseListed(DeskTicketResource::class)->getMessage(),
        );
        $this->assertSame(
            DeskTicketResource::class.'::instructions() lists '.AbstractDeskInstruction::class.', which is abstract or cannot be instantiated. List a concrete class.',
            InvalidInstructionException::notInstantiable(DeskTicketResource::class, AbstractDeskInstruction::class)->getMessage(),
        );
        $this->assertSame(
            'The container builds ['.NewestFirst::class.'] for '.DeskTitleMatch::class.', listed in '.DeskTicketResource::class.'::instructions(): bind '.DeskTitleMatch::class.' to itself or to a class that extends it.',
            InvalidInstructionException::foreignBinding(DeskTicketResource::class, DeskTitleMatch::class, NewestFirst::class)->getMessage(),
        );
    }

    #[Test]
    public function it_refuses_a_binding_that_builds_another_class(): void
    {
        $this->app->bind(DeskTitleMatch::class, fn (): NewestFirst => new NewestFirst);

        $this->expectExceptionObject(InvalidInstructionException::foreignBinding(DeskTicketResource::class, DeskTitleMatch::class, NewestFirst::class));

        $this->desk()->definition()->instructions();
    }

    #[Test]
    public function it_registers_the_routes_without_reading_the_instructions(): void
    {
        DeskTicketResource::$moreInstructions = [AbstractDeskInstruction::class];

        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->postJson('/desk-tickets/metrics/ticket-backlog')->assertOk();
        $this->assertSame([DeskTicketBacklog::class], DeclarationLog::$built);
        $this->expectExceptionObject(InvalidInstructionException::notInstantiable(DeskTicketResource::class, AbstractDeskInstruction::class));

        $this->desk()->definition()->instructions();
    }

    #[Test]
    public function it_builds_and_counts_every_instruction_in_the_check(): void
    {
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        Artisan::call('rest:check', ['controllers' => [DeskTicketController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertContains(DeskTitleMatch::class, DeclarationLog::$built);
        $this->assertSame(['actions' => 4, 'metrics' => 3, 'instructions' => 3, 'problems' => []], $report['declarations']);
    }

    #[Test]
    public function it_reports_the_instructions_that_cannot_be_built_in_the_check(): void
    {
        DeskTicketResource::$moreInstructions = [LabelledDeskInstruction::class, AbstractDeskInstruction::class];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        $unresolvable = DeskTicketResource::class.': Unresolvable dependency resolving [Parameter #0 [ <required> string $label ]] in class '.LabelledDeskInstruction::class;
        $abstract = InvalidInstructionException::notInstantiable(DeskTicketResource::class, AbstractDeskInstruction::class)->getMessage();

        $exitCode = Artisan::call('rest:check', ['controllers' => [DeskTicketController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertSame(1, $exitCode);
        $this->assertSame(['actions' => 4, 'metrics' => 3, 'instructions' => 3, 'problems' => [$unresolvable, $abstract]], $report['declarations']);

        Artisan::call('rest:check', ['controllers' => [DeskTicketController::class]], $output = new BufferedOutput);
        $text = $output->fetch();

        $this->assertMatchesRegularExpression('/  declarations \.+ FAILED  \n  ⇂ /u', $text);
        $this->assertStringContainsString("  ⇂ {$unresolvable}  \n", $text);
        $this->assertStringContainsString('  ⇂ '.rtrim($abstract, '.')."  \n", $text);
    }

    #[Test]
    public function it_builds_no_class_instruction_for_a_search_without_instructions(): void
    {
        $this->seedTickets();
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $this->postJson('/desk-tickets/search', ['search' => ['sorts' => [['field' => 'id', 'direction' => 'asc']]]])
            ->assertOk()
            ->assertJsonPath('data.*.title', ['Printer jam', 'Printer toner']);

        $this->assertSame([], DeclarationLog::$built);

        $this->postJson('/desk-tickets/search', ['search' => ['instructions' => [['name' => 'title-match']]]])->assertOk();

        $this->assertSame([DeskTitleMatch::class], DeclarationLog::$built);
    }

    #[Test]
    public function it_runs_a_class_instruction_of_an_included_resource(): void
    {
        $tickets = $this->seedTickets();
        $hardware = Tag::create(['name' => 'Hardware']);
        $tickets['Printer jam']->tags()->attach($hardware, ['position' => 1]);
        $tickets['Printer jam']->tags()->attach(Tag::create(['name' => 'Software']), ['position' => 2]);
        $tickets['Printer toner']->tags()->attach($hardware, ['position' => 1]);
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        $rows = $this->postJson('/desk-tickets/search', ['search' => [
            'sorts' => [['field' => 'id', 'direction' => 'asc']],
            'includes' => [['relation' => 'tags', 'instructions' => [['name' => 'tag-match', 'fields' => [['name' => 'word', 'value' => 'Soft']]]]]],
        ]])
            ->assertOk()
            ->json('data');

        $this->assertSame([['Software'], []], array_map(fn (array $row): array => array_column($row['tags'], 'name'), $rows));
        $this->assertSame([DeskTagMatch::class], DeclarationLog::$built);
        $this->assertSame(['matched tag Soft'], DeclarationLog::$journal);
    }

    #[Test]
    public function it_runs_a_class_instruction_in_the_search_of_an_action_and_of_a_metric(): void
    {
        $tickets = $this->seedTickets();
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        $this->postJson('/desk-tickets/actions/close-tickets', ['search' => ['instructions' => [['name' => 'title-match', 'fields' => [['name' => 'word', 'value' => 'toner']]]]]])
            ->assertOk()
            ->assertExactJson(['data' => ['impacted' => 1, 'result' => null]]);
        $this->postJson('/desk-tickets/metrics/ticket-backlog', ['search' => ['instructions' => [['name' => 'title-match']]]])
            ->assertOk()
            ->assertExactJson(['data' => ['type' => 'gauge', 'value' => 1, 'max' => 10]]);

        $this->assertSame('open', $tickets['Printer jam']->fresh()?->status);
        $this->assertSame('closed', $tickets['Printer toner']->fresh()?->status);
        $this->assertSame(['matched toner', 'closed 1', 'matched jam'], DeclarationLog::$journal);
    }

    #[Test]
    public function it_reports_a_broken_instruction_of_a_resource_reached_through_a_relation_in_the_check(): void
    {
        DeskTagResource::$broken = true;
        Rest::resource('desk-tickets', DeskTicketController::class)->register();
        $unresolvable = DeskTagResource::class.': Unresolvable dependency resolving [Parameter #0 [ <required> string $label ]] in class '.LabelledDeskInstruction::class;

        $exitCode = Artisan::call('rest:check', ['controllers' => [DeskTicketController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertSame(1, $exitCode);
        $this->assertSame(['actions' => 4, 'metrics' => 3, 'instructions' => 3, 'problems' => [$unresolvable]], $report['declarations']);

        Artisan::call('rest:check', ['controllers' => [DeskTicketController::class]], $output = new BufferedOutput);

        $this->assertStringContainsString("  ⇂ {$unresolvable}  \n", $output->fetch());
    }

    #[Test]
    public function it_names_the_resource_once_in_each_instruction_problem_of_the_check(): void
    {
        DeskTicketResource::$moreInstructions = [EngineOnlyDeskInstruction::class, Instruction::class];
        Rest::resource('desk-tickets', DeskTicketController::class)->register();

        Artisan::call('rest:check', ['controllers' => [DeskTicketController::class], '--json' => true], $output = new BufferedOutput);
        $report = json_decode($output->fetch(), true);

        $this->assertSame([
            DeskTicketResource::class.': '.InvalidInstructionException::engineOnly(EngineOnlyDeskInstruction::class)->getMessage(),
            InvalidInstructionException::baseListed(DeskTicketResource::class)->getMessage(),
        ], $report['declarations']['problems']);
    }

    private function desk(): Resource
    {
        return $this->contextFor(null)->resource(DeskTicketResource::class);
    }
}
