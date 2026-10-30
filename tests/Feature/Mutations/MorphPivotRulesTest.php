<?php

namespace Lomkit\Rest\Tests\Feature\Mutations;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\RuledTagController;
use Lomkit\Rest\Tests\Feature\Mutations\Stubs\RuledTicketController;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class MorphPivotRulesTest extends PackageTestCase
{
    private Ticket $jam;

    private Tag $hardware;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Rest::resource('ruled-tickets', RuledTicketController::class)->register();
        Rest::resource('ruled-tags', RuledTagController::class)->register();

        $billing = Team::create(['name' => 'Billing']);
        $this->jam = Ticket::create(['team_id' => $billing->id, 'title' => 'Printer jam', 'status' => 'open']);
        $this->hardware = Tag::create(['name' => 'Hardware']);
    }

    #[Test]
    #[DataProvider('pivotOperations')]
    public function it_applies_the_resource_update_rules_on_sync_and_toggle_of_a_morph_to_many_relation(string $operation): void
    {
        $response = $this->postJson('/ruled-tickets/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->jam->id,
            'relations' => ['labels' => [['operation' => $operation, 'key' => $this->hardware->id, 'attributes' => ['name' => 'Hardware and peripherals']]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.labels.0.attributes.name' => ['The mutate.0.relations.labels.0.attributes.name field must not be greater than 20 characters.'],
        ]);
        $this->assertSame('Hardware', $this->hardware->fresh()?->name);
        $this->assertSame([], $this->jam->labels()->pluck('tags.id')->all());
    }

    #[Test]
    #[DataProvider('pivotOperations')]
    public function it_applies_the_resource_update_rules_on_sync_and_toggle_of_a_morphed_by_many_relation(string $operation): void
    {
        $response = $this->postJson('/ruled-tags/mutate', ['mutate' => [[
            'operation' => 'update',
            'key' => $this->hardware->id,
            'relations' => ['labelledTickets' => [['operation' => $operation, 'key' => $this->jam->id, 'attributes' => ['title' => 'Printer jam on the third floor']]]],
        ]]]);

        $response->assertUnprocessable()->assertJsonPath('errors', [
            'mutate.0.relations.labelledTickets.0.attributes.title' => ['The mutate.0.relations.labelled tickets.0.attributes.title field must not be greater than 20 characters.'],
        ]);
        $this->assertSame('Printer jam', $this->jam->fresh()?->title);
        $this->assertSame([], $this->hardware->labelledTickets()->pluck('tickets.id')->all());
    }

    /** @return array<string, array{string}> */
    public static function pivotOperations(): array
    {
        return ['sync' => ['sync'], 'toggle' => ['toggle']];
    }
}
