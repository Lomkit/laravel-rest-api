<?php

namespace Lomkit\Rest\Tests\Feature\Translations;

use Illuminate\Auth\Access\Response;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\ChunkedTicketController;
use Lomkit\Rest\Tests\Feature\Actions\Stubs\CloseOpenTickets;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\KeptTicketController;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\VersionedProductController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Helpdesk;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Tag;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use PHPUnit\Framework\Attributes\Test;

class FrenchMessagesTest extends HelpdeskTestCase
{
    use AssertsExactErrors;

    private const array DESK_LAMP = ['field' => 'name', 'value' => 'Desk lamp'];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Helpdesk, new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('fr');
    }

    #[Test]
    public function it_answers_a_limit_in_french(): void
    {
        config(['rest.limits.totals.filters' => 1]);

        $response = $this->postJson('/api/products/search', ['search' => ['filters' => [self::DESK_LAMP, self::DESK_LAMP]]]);

        $this->assertExactErrors($response, ['search.filters' => ['Le champ search.filters dépasse la limite de 1 filtres par recherche.']]);
    }

    #[Test]
    public function it_answers_the_response_rows_limit_in_french(): void
    {
        config(['rest.limits.sizes.rows' => 9]);

        $response = $this->postJson('/api/products/search', ['search' => ['limit' => 10]]);

        $this->assertExactErrors($response, ['search.limit' => ['Le champ search.limit fait dépasser à la réponse la limite de 9 lignes : elle peut en rendre jusqu\'à 10.']]);
    }

    #[Test]
    public function it_answers_the_pattern_filter_limit_in_french(): void
    {
        config(['rest.limits.totals.patterns' => 1]);
        $pattern = ['field' => 'name', 'operator' => 'like', 'value' => 'Desk%'];

        $response = $this->postJson('/api/products/search', ['search' => ['filters' => [$pattern, $pattern]]]);

        $this->assertExactErrors($response, ['search.filters' => ['Le champ search.filters dépasse la limite de 1 filtres de motif par recherche.']]);
    }

    #[Test]
    public function it_answers_a_sync_that_would_detach_rows_in_french(): void
    {
        Rest::resource('kept-tickets', KeptTicketController::class)->register();
        $jam = $this->seedTickets()['Printer jam'];

        $response = $this->postJson('/kept-tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $jam->id, 'relations' => ['tags' => [['operation' => 'sync', 'key' => []]]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.tags.0.without_detaching' => ['Le champ mutate.0.relations.tags.0.without_detaching doit valoir true : cette relation ne détache pas de lignes.']]);
    }

    #[Test]
    public function it_answers_a_detach_of_a_required_foreign_key_in_french(): void
    {
        $jam = $this->seedTickets()['Printer jam'];

        $response = $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $jam->id, 'relations' => ['team' => ['operation' => 'detach', 'key' => $jam->team_id]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.team.key' => ['Le champ mutate.0.relations.team.key est interdit : cette relation ne peut pas être détachée, sa colonne de lien n\'accepte pas null.']]);
    }

    #[Test]
    public function it_answers_an_unknown_relation_key_in_french(): void
    {
        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Seating'], 'relations' => ['suppliers' => []]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations' => ['La clé suppliers n\'est pas valide pour le champ mutate.0.relations.']]);
    }

    #[Test]
    public function it_answers_the_version_conflict_in_french(): void
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
    public function it_answers_the_row_cap_of_an_action_in_french(): void
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
    public function it_answers_a_denial_without_message_in_french(): void
    {
        $this->renderDenials();
        $this->teams->answers['viewAny'] = Response::deny();

        $response = $this->postJson('/api/tickets/search', ['search' => ['includes' => [['relation' => 'team']]]]);

        $response->assertForbidden()->assertExactJson([
            'message' => 'Vous n\'êtes pas autorisé à effectuer cette action.',
            'denials' => [['path' => 'search.includes.0', 'ability' => 'viewAny', 'message' => 'Vous n\'êtes pas autorisé à effectuer cette action.']],
        ]);
    }

    #[Test]
    public function it_answers_an_attached_record_in_french(): void
    {
        $jam = $this->seedTickets()['Printer jam'];
        $hardware = Tag::create(['name' => 'Hardware']);
        $jam->tags()->attach([$hardware->id]);

        $response = $this->postJson('/api/tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => $jam->id, 'relations' => ['tags' => [['operation' => 'attach', 'key' => [$hardware->id]]]]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations.tags.0.key' => ['Le champ mutate.0.relations.tags.0.key contient un élément déjà attaché. Utilisez update ou sync pour modifier son pivot.']]);
    }

    #[Test]
    public function it_answers_with_a_line_that_the_application_replaces(): void
    {
        $this->app->make('translator')->addLines(['validation.unknown_key' => 'La relation :key est inconnue sous :attribute.'], 'fr', 'rest');

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Seating'], 'relations' => ['suppliers' => []]],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations' => ['La relation suppliers est inconnue sous mutate.0.relations.']]);
    }
}
