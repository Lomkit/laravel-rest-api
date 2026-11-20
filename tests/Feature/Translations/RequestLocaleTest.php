<?php

namespace Lomkit\Rest\Tests\Feature\Translations;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CloseOpenTickets;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\VersionedProductController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;

class RequestLocaleTest extends HelpdeskTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk, new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $translator = $this->app->make('translator');
        $translator->addLines(['validation.unknown_key' => 'La clé :key n\'est pas valide pour le champ :attribute.'], 'fr', 'rest');
        $translator->addLines(['errors.version_conflict' => 'L\'élément a été modifié par une autre requête.'], 'fr', 'rest');
        $translator->addLines(['actions.row_cap' => 'Cette action porte sur :max ressources au plus : affinez la recherche.'], 'fr', 'rest');
        $this->app->setLocale('fr');
    }

    #[Test]
    public function it_answers_a_validation_message_with_the_lines_of_the_application(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Seating'], 'relations' => ['suppliers' => []]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations' => ['La clé suppliers n\'est pas valide pour le champ mutate.0.relations.']]);
    }

    #[Test]
    public function it_answers_an_error_message_with_the_lines_of_the_application(): void
    {
        $this->travelTo('2026-03-01 10:00:00');
        Rest::resource('versioned-products', VersionedProductController::class)->register();
        $lighting = Category::factory()->create(['name' => 'Lighting']);
        $lamp = Product::factory()->create(['category_id' => $lighting->id, 'name' => 'Desk lamp', 'price' => 39, 'updated_at' => '2026-01-01 08:00:00']);
        $lamp->update(['name' => 'Desk light']);

        $response = $this->postJson('/versioned-products/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $lamp->id, 'version' => '2026-01-01T08:00:00.000000Z', 'attributes' => ['price' => 42]],
        ]]);

        $response->assertConflict()->assertExactJson([
            'message' => 'L\'élément a été modifié par une autre requête.',
            'conflicts' => [['reason' => 'version', 'path' => 'mutate.0', 'key' => $lamp->id, 'version' => '2026-03-01T10:00:00.000000Z']],
        ]);
    }

    #[Test]
    public function it_answers_an_action_message_with_the_lines_of_the_application(): void
    {
        CloseOpenTickets::reset();
        Rest::resource('chunked-tickets', ChunkedTicketController::class)->register();
        Team::create(['id' => 1, 'name' => 'Support']);

        foreach (range(1, 6) as $id) {
            Ticket::create(['id' => $id, 'team_id' => 1, 'title' => 'Ticket '.$id, 'status' => 'open']);
        }

        $response = $this->postJson('/chunked-tickets/actions/capped-close-open-tickets', ['search' => []]);

        $this->assertExactErrors($response, ['search' => ['Cette action porte sur 5 ressources au plus : affinez la recherche.']]);
    }

    #[Test]
    public function it_answers_in_english_a_line_that_the_application_does_not_translate(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['includes' => [['relation' => 'suppliers']]]]);

        $this->assertExactErrors($response, [
            'search.includes.0.relation' => ['The search.includes.0.relation field is not a valid or allowed relation for this resource.'],
        ]);
    }
}
